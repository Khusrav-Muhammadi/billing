<?php

namespace App\Jobs;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendToShamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $phone,
        public ?string $tariff = null,
        public string $email,
        public string $name,
        public ?string $region = null,
        public ?string $partner = null
    ) {}

    public function handle(): void
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post('https://sham-back.shamcrm.com/api/messengerSettings/site/webhook', [
            'phone' => $this->phone,
            'tarif' => $this->tariff ?? '',
            'email' => $this->email,
            'name_company' => $this->name,
            'region' => $this->region ?? '',
            'partner' => $this->partner ?? '',
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
