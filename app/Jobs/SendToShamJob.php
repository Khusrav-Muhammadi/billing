<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use function Termwind\renderUsing;

class SendToShamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $phone,
        public ?string $tariff = null,
        public string $email,
        public string $name,
        public ?string $region = null,
        public ?int $partnerId = null
    ) {}

    public function handle(): void
    {
        $partner = User::find($this->partnerId);

        $defaultLink = $this->region === 'Узбекистан'
            ? 'https://sham-back.shamcrm.com'
            : 'https://fingroupcrm-back.shamcrm.com';

        $shamLink = $partner?->sham_link ?? $defaultLink;

        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($shamLink . '/api/messengerSettings/site/webhook', [
            'phone' => $this->phone,
            'tarif' => 'VIP',
            'email' => $this->email,
            'name_company' => $this->name,
            'region' => $this->region ?? '',
            'partner' => $partner?->name ?? '',
        ]);

        if (!$response->successful()) {
            Log::error('Ошибка при отправке в Sham API', [
                'phone' => $this->phone,
                'response' => $response->body(),
            ]);

            throw new \Exception('Ошибка при отправке в Sham API');
        }
    }
}
