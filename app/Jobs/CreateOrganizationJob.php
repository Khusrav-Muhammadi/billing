<?php

namespace App\Jobs;

use App\Mail\SendSiteDataMail;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Tariff;
use App\Models\TariffCurrency;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreateOrganizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Client $client, public Organization $organization, public string $password)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $domain = env('APP_DOMAIN');
        $url = "https://{$this->client->sub_domain}-back.{$domain}/api/organization";
     //   $url = "https://hello-back.sham360.com/api/organization";




        $tariff = TariffCurrency::find($this->client->tariff_id)->tariff;

        Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($url, [
            'name' => $this->organization->name,
            'email' => $this->client->email,
            'phone' => $this->client->phone,
            'tariff_id' => $tariff->id,
            'user_count' => $tariff->user_count,
            'project_count' => $tariff->project_count,
            'b_organization_id' => $this->organization->id,
            'password' => $this->password,
            'is_demo' => $this->client->is_demo,
            'channels_count' => $tariff->channels_count ?? 3,
        ]);

        if (Organization::where('client_id', $this->client->id)->count() == 1) Mail::to($this->client->email)->send(new SendSiteDataMail($this->client, $this->password));
    }
}
