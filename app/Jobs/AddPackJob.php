<?php

namespace App\Jobs;

use App\Models\CommercialOffer;
use App\Models\Tariff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class AddPackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public CommercialOffer $offer, public string $sub_domain, public Tariff $tariff)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $domain = env('APP_DOMAIN');
        $url = 'https://' . $this->sub_domain . '-back.' . $domain . '/api/organization/add-pack';

        $data = [
            'type' => $this->tariff->type,
            'b_organization_id' => $this->offer->organization_id,
        ];

        if ($this->tariff->type == 'user') {
            $data['amount'] = 1;
        }

        if ($this->tariff->type == 'add_sales_funnel') {
            $data['amount'] = 1;
        }

        if ($this->tariff->type == 'add_channel') {
            $data['amount'] = 1;
        }

        if ($this->tariff->type == 'add_insta_channel') {
            $data['amount'] = 1;
        }

        if ($this->tariff->type == 'add_mini_app_b2b') {
            $data['amount'] = 1;
        }

        if ($this->tariff->type == 'add_mini_app_b2c') {
            $data['amount'] = 1;
        }

        Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($url, $data);

    }
}
