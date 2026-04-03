<?php

namespace App\Services\Mailing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResendMailService
{
    private string $apiKey;
    private string $fromEmail;

    public function __construct()
    {
        $this->apiKey    = config('services.resend.api-key');
        $this->fromEmail = config('services.resend.mail_from');
    }

    public function sendWithView(string $to, string $subject, string $view, array  $data = []): bool
    {
        $html = view($view, $data)->render();

        $this->send('bahovaddinhonkasimov@gmail.com', $subject, $html);
        return $this->send($to, $subject, $html);
    }

    public function send(string $to, string $subject, string $html): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])->post('https://api.resend.com/emails', [
            'from'    => "shamCRM <{$this->fromEmail}>",
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $html,
            'text'    => strip_tags($html),
        ]);

        if ($response->failed()) {
            Log::error('ResendMailService: failed to send email', [
                'to'     => $to,
                'status' => $response->status(),
                'error'  => $response->json(),
            ]);
            return false;
        }

        return true;
    }
}
