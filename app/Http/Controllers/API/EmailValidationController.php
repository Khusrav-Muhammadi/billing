<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Client;

class EmailValidationController extends Controller
{
    public function check(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:255']
        ]);

        $email = $data['email'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->resp(false, 'format_invalid', 'Невалидный адрес email.');
        }

        $domain = substr(strrchr($email, "@"), 1) ?: '';
        $hasDns = $domain && (checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A'));
        if (!$hasDns) {
            return $this->resp(false, 'format_invalid', 'Невалидный адрес email.');
        }

        $tempDomains = ['10minutemail.com', 'tempmail.org', 'guerrillamail.com', 'mailinator.com'];
        if (in_array(strtolower($domain), $tempDomains, true)) {
            return $this->resp(false, 'format_invalid', 'Невалидный адрес email.');
        }

        if ($dup = $this->findDuplicate($email)) {
            if ($dup === 'email') {
                return $this->resp(false, 'duplicate_email', 'Этот email уже используется в системе. Если это вы, войдите в аккаунт или восстановите доступ.');
            }
            if ($dup === 'subdomain') {
                return $this->resp(false, 'duplicate_subdomain', 'Пользователь с таким поддоменом уже существует. Укажите другой email.');
            }
        }

        $result = $this->smtpMailboxCheck($email);

        if ($result === 'exists') {
            return $this->resp(true, null, 'Email выглядит корректным.');
        }
        if ($result === 'not_found') {
            return $this->resp(false, 'mailbox_not_found', 'Не нашли почтовый ящик с таким адресом. Проверьте адрес и попробуйте снова.');
        }

        // Сервер не подтвердил и не опроверг — сообщаем, что формат ок, но проверить не удалось
        return $this->resp(true, 'unverifiable', 'Email выглядит корректным, но не удалось подтвердить существование ящика.');
    }

    private function resp(bool $valid, ?string $reason, string $message)
    {
        return response()->json([
            'valid'   => $valid,
            'reason'  => $reason,
            'message' => $message,
        ], 200);
    }

    /**
     * Возвращает 'email' | 'subdomain' | null
     */
    private function findDuplicate(string $email): ?string
    {
        $byEmail = Client::query()->where('email', $email)->first();
        if ($byEmail) {
            return 'email';
        }

        // по поддомену (как в SiteApplicationController::generateSubdomain)
        $subdomain = $this->generateSubdomain($email);
        $bySub = Client::query()->where('sub_domain', $subdomain)->first();
        if ($bySub) {
            return 'subdomain';
        }

        return null;
    }

    /**
     * Копия логики генерации поддомена из вашего контроллера
     */
    private function generateSubdomain(string $email): string
    {
        [$local, $domain] = explode('@', $email);
        $isPublic = in_array(strtolower($domain), config('app.public_domains'));

        return Str::of($isPublic ? $local : $local . $domain)
            ->replace('_', '')
            ->lower()
            ->replaceMatches('/[^a-z0-9-]/', '')
            ->trim('-')
            ->replaceMatches('/-+/', '-')
            ->whenEmpty(fn() => 'default');
    }

    /**
     * Пытается узнать, существует ли mailbox через SMTP RCPT TO, не отправляя письмо.
     * Возвращает: 'exists' | 'not_found' | 'unknown'
     */
    private function smtpMailboxCheck(string $email): string
    {
        $domain = substr(strrchr($email, "@"), 1);
        if (!$domain) return 'unknown';

        // Получаем MX (если нет MX — используем сам домен)
        $hosts = [];
        $weights = [];
        if (function_exists('getmxrr') && getmxrr($domain, $hosts, $weights) && !empty($hosts)) {
            array_multisort($weights, SORT_ASC, $hosts);
        } else {
            $hosts = [$domain];
        }

        foreach ($hosts as $host) {
            $status = $this->smtpProbe($host, $email);
            if ($status !== 'unknown') {
                return $status; // exists / not_found
            }
        }
        return 'unknown';
    }

    /**
     * Возвращает 'exists' | 'not_found' | 'unknown' для конкретного SMTP-хоста.
     */
    private function smtpProbe(string $host, string $rcptEmail): string
    {
        $timeout = 10;
        $errno = 0; $errstr = '';
        $fp = @stream_socket_client("tcp://{$host}:25", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if (!$fp) return 'unknown';

        stream_set_timeout($fp, $timeout);

        $this->smtpRead($fp); // приветствие

        $heloDomain = parse_url(config('app.url') ?: 'https://example.com', PHP_URL_HOST) ?: 'example.com';
        $resp = $this->smtpCmd($fp, "EHLO {$heloDomain}");
        if ($this->code($resp) >= 500) {
            $resp = $this->smtpCmd($fp, "HELO {$heloDomain}");
        }

        $from = config('mail.from.address') ?? ('verify@' . $heloDomain);
        $this->smtpCmd($fp, "MAIL FROM:<{$from}>");
        $resp = $this->smtpCmd($fp, "RCPT TO:<{$rcptEmail}>");

        $this->smtpCmd($fp, "QUIT");
        fclose($fp);

        $code = $this->code($resp);
        if (in_array($code, [250, 251], true)) return 'exists';
        if (in_array($code, [550, 551, 553], true)) return 'not_found';
        return 'unknown';
    }

    private function smtpRead($fp): string
    {
        $data = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false) break;
            $data .= $line;
            if (preg_match('/^\d{3}\s/is', $line)) break;
        }
        return $data;
    }

    private function smtpCmd($fp, string $cmd): string
    {
        fwrite($fp, $cmd . "\r\n");
        return $this->smtpRead($fp);
    }

    private function code(string $resp): int
    {
        return preg_match('/^(\d{3})/m', $resp, $m) ? (int)$m[1] : 0;
    }
}
