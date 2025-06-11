<?php

namespace App\Jobs;

use App\Events\ClientHistoryEvent;
use App\Events\OrganizationHistoryEvent;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationPack;
use App\Models\Tariff;
use App\Services\WithdrawalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ActivationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public array $organizationIds, public string $domain, public bool $activation,
                                public bool  $updateClient = false, public int $authId = 1, public string $reject_cause = '')
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $domain = env('APP_DOMAIN');
        $url = 'https://' . $this->domain . '-back.' . $domain . '/api/organization/activation';

        $data = [
            'b_organizations' => $this->organizationIds,
            'has_access' => $this->activation
        ];

        $res = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($url, $data);

        if ($res->successful()) {
            DB::transaction(function () {
                $organizations = Organization::whereIn('id', $this->organizationIds)->get();

                foreach ($organizations as $organization) {
                    $organization->update([
                        'has_access' => $this->activation,
                        'reject_cause' => $this->reject_cause
                    ]);
                }
                if (count($this->organizationIds) == 1) {
                    Organization::query()->whereIn('id', $this->organizationIds)->update(['has_access' => true]);
                }

                $client = Organization::query()->whereIn('id', $this->organizationIds)->first()->client;
                $client->is_active = true;
                $client->save();

                if ($this->activation) {
                    $organization = Organization::whereIn('id', $this->organizationIds)->first();
                    $service = new WithdrawalService();
                    $sum = $service->countSum($organization->client()->first());

                    $service->handle($organization, $sum);

                }
            });
        }


        if ($this->activation) {
            if ($this->organizationIds == []) return;
            $organization = Organization::whereIn('id', $this->organizationIds)->first();
            $client = $organization->client()->first();

            $client->is_active = !$client->is_active;
            $client->reject_cause = $this->reject_cause;
            $client->save();

        }
    }
}
