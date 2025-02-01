<?php

namespace App\Jobs;

use App\Events\OrganizationHistoryEvent;
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

class ActivationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public array $organizationIds, public string $domain, public bool $activation, public bool $updateClient = false, public int $authId = 1)
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

        if ($res->successful())
            DB::transaction(function () {
                $organizations = Organization::whereIn('id', $this->organizationIds)->get();

                foreach ($organizations as $organization) {
                    $organization->update(['has_access' => $this->activation]);
                    OrganizationHistoryEvent::dispatch($organization, $this->authId);
                }

                if ($this->activation) {
                    $organization = Organization::whereIn('id', $this->organizationIds)->first();
                    $service = new WithdrawalService();
                    $sum = $service->countSum($organization->client()->first());
                    $service->handle($organization, $sum);
                }
            });

        if ($this->updateClient) {
            if ($this->organizationIds == []) return;
            $organization = Organization::whereIn('id', $this->organizationIds)->first();
            $client = $organization->client()->first();

            $client->disableObserver = true;
            $client->update(['is_active' => !$client->is_active]);
        }
    }
}
