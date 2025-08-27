<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\EmailExistingClientInfoJob;
use App\Jobs\NewErrorMessageJob;
use App\Jobs\NewSiteRequestJob;
use App\Jobs\SendDemoWelcomeEmailJob;
use App\Jobs\SendToShamJob;
use App\Jobs\SubDomainJob;
use App\Models\Client;
use App\Models\Country;
use App\Models\SiteApplications;
use App\Models\Tariff;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\ClientRepository;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\OrganizationRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;


class SiteApplicationController extends Controller
{
    public function index()
    {
        $applications = SiteApplications::all();
        return view('admin.site-applications.index', compact('applications'));
    }

    /**
     * store() больше НЕ валидирует email через внешний API и
     * НЕ отправляет писем при ошибках/дубликатах email.
     */
    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        if ($validated['request_type'] === 'demo') {


            $conflict = $this->checkExistingClient($validated);
            if ($conflict) {
                NewErrorMessageJob::dispatch($conflict['message'], [
                    'email' => $validated['email'],
                    'phone' => $validated['phone']
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $conflict['message']
                ], 409);
            }

            $client = $this->createDemoClient($validated);
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Не удалось создать демо-аккаунт. Попробуйте позже.'
                ], 500);
            }

       //     SendDemoWelcomeEmailJob::dispatch($client);
            SubDomainJob::dispatch($client);

            $data = [
                'name'       => $client->name,
                'phone'      => $client->phone,
                'client_id'  => $client->id,
                'has_access' => true,
                'partner_id' => $validated['partner_id'],
                'country_id' => $validated['region_id']
            ];
            (new \App\Repositories\OrganizationRepository())->store($client, $data);

            SendToShamJob::dispatch(
                $client->phone,
                $client->tariff?->name,
                $client->email,
                $client->name,
                $client->country?->name,
                $client->partner?->name
            );

        } elseif ($validated['request_type'] === 'individual') {

            SendToShamJob::dispatch(
                $validated['phone'],
                'Тариф: Индивидуальный тариф',
                $validated['email'],
                $validated['fio'],
                Country::find($validated['region_id'])?->name
            );

        } else {
            $this->createRegularApplication($validated);
            NewSiteRequestJob::dispatch(User::first(), $validated['request_type']);
        }

        return response()->json(['success' => true]);
    }

    /**
     * НОВЫЙ API: проверка email.
     * - формат и длина (Laravel)
     * - дубликат по email
     * - дубликат по subdomain (как генерится в системе)
     * - «реальность» ящика (внешний сервис + DNS), с fallback’ом
     */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|string|max:255',
        ], [
            'email.required' => 'Поле email обязательно.',
            'email.max'      => 'Поле email не должно превышать 255 символов.',
        ]);

        $raw   = trim((string)$request->input('email'));
        $email = mb_strtolower($raw);

        $atPos = strrpos($email, '@');
        if ($atPos === false || $atPos === mb_strlen($email) - 1) {
            return response()->json([
                'success' => false,
                'reason'  => 'invalid_format',
                'message' => 'Пожалуйста введите правильный адрес почты.',
            ], 422);
        }

        $domain = substr($email, $atPos + 1);

        $asciiDomain = function_exists('idn_to_ascii')
            ? (idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $domain)
            : $domain;

        $subdomain = $this->generateSubdomain($email);
        if (Client::query()->where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'reason'  => 'email_exists',
                'message' => 'Этот email уже используется в системе.',
            ], 409);
        }
        if (Client::query()->where('sub_domain', $subdomain)->exists()) {
            return response()->json([
                'success' => false,
                'reason'  => 'subdomain_exists',
                'message' => 'Пользователь с таким поддоменом уже существует.',
            ], 409);
        }

        if (!$this->hasDns($asciiDomain)) {
            return response()->json([
                'success' => false,
                'reason'  => 'dns_not_found',
                'message' => 'У домена нет MX/A записей. Адрес недоставляем.',
                'details' => ['domain' => $domain],
            ], 422);
        }

        $formatValid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        if (!$formatValid) {
            return response()->json([
                'success' => false,
                'reason'  => 'invalid_format',
                'message' => 'Пожалуйста введите правильный адрес почты.',
            ], 422);
        }


        $api = $this->validateWithApi($email);
        if (!$api['deliverable']) {
            return response()->json([
                'success' => false,
                'reason'  => 'not_deliverable',
                'message' => 'Указанный email адрес не является действительным.',
                'details' => $api,
            ], 422);
        }

        return response()->json([
            'success'   => true,
            'email'     => $email
        ]);
    }

    /** MX/A check с поддержкой IDN (домейн сюда уже в ASCII). */
    private function hasDns(string $asciiDomain): bool
    {
        // MX предпочтительнее; если нет — допускаем A как fallback
        return checkdnsrr($asciiDomain, 'MX') || checkdnsrr($asciiDomain, 'A');
    }

    /** Внешняя проверка ТОЛЬКО как последний шаг. Возвращает метаданные и флаг, использовали ли API. */
    private function validateWithApi(string $email): array
    {
        $primaryKey = 'ema_live_pLU3hBeUBOIc2NJqRuJOt6wh2TsqDilv9FBduowR';
        $backupKey = 'ema_live_jz1XQ5zo1GQ27BBO8siKvpZyxHgb5hgtROsJmXgj';

        try {
            $resp = $this->sendEmailValidationRequest($email, $primaryKey);
            if ($resp->status() === 429 && !empty($backupKey)) {
                $resp = $this->sendEmailValidationRequest($email, $backupKey);
            }

            if ($resp->successful()) {
                $data        = $resp->json();
                $deliverable = (($data['state'] ?? '') === 'deliverable');

                return [
                    'api_used'     => true,
                    'deliverable'  => $deliverable,
                    'state'        => $data['state'] ?? null,
                    'format_valid' => (bool)($data['format_valid'] ?? false),
                    'smtp_check'   => (bool)($data['smtp_check'] ?? false),
                    'disposable'   => (bool)($data['disposable'] ?? false),
                ];
            }

            return [
                'api_used'     => false,
                'deliverable'  => null,
                'state'        => null,
                'format_valid' => null,
                'smtp_check'   => null,
                'disposable'   => null,
            ];
        } catch (\Throwable $e) {
            return [
                'api_used'     => false,
                'deliverable'  => null,
                'state'        => null,
                'format_valid' => null,
                'smtp_check'   => null,
                'disposable'   => null,
            ];
        }
    }

    private function checkExistingClient(array $data): ?array
    {
        $email = $data['email'] ?? null;
        $subdomain = $email ? $this->generateSubdomain($email) : null;

        if ($email) {
            $clientByEmail = Client::query()->where('email', $email)->first();
            if ($clientByEmail) {
                return [
                    'reason'  => 'email',
                    'client'  => $clientByEmail,
                    // Текст больше НЕ обещает отправку письма:
                    'message' => 'Этот email уже используется в системе. Если это вы — восстановите доступ через форму входа.',
                ];
            }
        }

        if ($subdomain) {
            $clientBySub = Client::query()->where('sub_domain', $subdomain)->first();
            if ($clientBySub) {
                return [
                    'reason'  => 'subdomain',
                    'client'  => $clientBySub,
                    'message' => 'Пользователь с таким поддоменом уже существует.',
                ];
            }
        }

        return null;
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'fio'          => 'required|string|max:255',
            'phone'        => 'required|string|max:20',
            'email'        => [ Rule::requiredIf($request->input('request_type') === 'demo'), 'email', 'max:255' ],
            'region_id'    => 'nullable|integer',
            'request_type' => 'required|string|in:demo,partner,corporate,individual',
            'partner_id'   => 'nullable',
            'manager_id'   => 'nullable',
        ], [
            'fio.required'          => 'Поле ФИО обязательно для заполнения.',
            'fio.string'            => 'Поле ФИО должно быть строкой.',
            'fio.max'               => 'Поле ФИО не должно превышать 255 символов.',
            'phone.required'        => 'Поле телефон обязательно для заполнения.',
            'phone.string'          => 'Поле телефон должно быть строкой.',
            'phone.max'             => 'Поле телефон не должно превышать 20 символов.',
            'email.required'        => 'Поле email обязательно для демо-аккаунта.',
            'email.email'           => 'Пожалуйста введите правильный адрес почты',
            'email.max'             => 'Поле email не должно превышать 255 символов.',
            'region_id.integer'     => 'Поле регион должно быть числом.',
            'request_type.required' => 'Поле тип заявки обязательно для заполнения.',
            'request_type.string'   => 'Поле тип заявки должно быть строкой.',
            'request_type.in'       => 'Поле тип заявки должно быть одним из: demo, partner, corporate, individual.',
        ]);
    }

    private function sendEmailValidationRequest(string $email, ?string $apiKey)
    {
        return Http::timeout(10)->get('https://api.emailvalidation.io/v1/info', [
            'apikey' => $apiKey,
            'email'  => $email,
        ]);
    }

    private function isNotTempDomain(string $domain): bool
    {
        $tempDomains = ['10minutemail.com', 'tempmail.org', 'guerrillamail.com', 'mailinator.com'];
        return !in_array(strtolower($domain), $tempDomains, true);
    }

    // (Опционально оставляем — вдруг используется ещё где-то)
    private function fallbackEmailValidation(string $email): bool
    {
        $domain     = substr(strrchr($email, "@"), 1);
        $hasRecords = checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
        $isNotTemp  = $this->isNotTempDomain($domain);
        return $hasRecords && $isNotTemp;
    }

    private function createDemoClient(array $data): ?Client
    {
        $countryId = $data['region_id'] ?? 1;

        $tariffId = match ($countryId) {
            2       => 8,
            default => 4,
        };

        $currency_id = match ($countryId) {
            2       => 2,
            default => 1,
        };

        $clientData = [
            'name'        => $data['fio'],
            'phone'       => $data['phone'],
            'email'       => $data['email'],
            'country_id'  => $countryId,
            'is_demo'     => true,
            'tariff_id'   => $tariffId,
            'sub_domain'  => $this->generateSubdomain($data['email']),
            'currency_id' => $currency_id,
            'manager_id'  => $data['manager_id'] ?? null,
            'partner_id'  => $data['partner_id'] ?? null,
        ];

        if (!empty($clientData['manager_id'])) {
            $manager = \App\Models\User::query()->find($clientData['manager_id']);
            if ($manager && !empty($manager->partner_id)) {
                $clientData['partner_id'] = $manager->partner_id;
            }
        }

        try {
            return Client::create($clientData);
        } catch (\Exception $e) {
            Log::error('Ошибка создания демо-клиента: ' . $e->getMessage(), $clientData);
            return null;
        }
    }

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
            ->whenEmpty(fn () => 'default');
    }

    private function createRegularApplication(array $data): void
    {
        SiteApplications::create([
            'fio'          => $data['fio'],
            'phone'        => $data['phone'],
            'email'        => $data['email'],
            'region_id'    => $data['region_id'],
            'request_type' => $data['request_type'],
        ]);
    }
}
