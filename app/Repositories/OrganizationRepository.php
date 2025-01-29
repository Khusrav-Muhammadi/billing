<?php

namespace App\Repositories;

use App\Jobs\ActivationJob;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationPack;
use App\Models\Tariff;
use App\Models\Transaction;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OrganizationRepository implements OrganizationRepositoryInterface
{
    public function store(Client $client, array $data)
    {
        $data['client_id'] = $client->id;

        return DB::transaction(function () use ($client, $data) {
            $organization = Organization::create($data);

            $res = $this->createInSham($organization, $client->sub_domain);

            if (!$res) $organization->delete();
            else {
                $daysInMonth = Carbon::now()->daysInMonth;

                $sum = $client->tariff->price / $daysInMonth;

                if (!$client->is_demo)
                    Transaction::create([
                        'client_id' => $client->id,
                        'organization_id' => $organization->id,
                        'tariff_id' => $client->tariff->id,
                        'sale_id' => $client->sale?->id,
                        'sum' => $sum,
                        'type' => 'Снятие',
                    ]);
            }
                return $organization;
            });
        }

    public function update(Organization $organization, array $data)
    {
        $organization->update($data);
    }

    public function access(Organization $organization)
    {
        $client = $organization->client()->first();

        ActivationJob::dispatch(array($organization->id), $client->sub_domain, !$organization->has_access);
    }

    public function addPack(Organization $organization, array $data)
    {
        $organizationPack = OrganizationPack::create([
            'organization_id' => $organization->id,
            'pack_id' => $data['pack_id'],
            'date' => $data['date'],
        ]);

        $res = $this->addPackInSham($organizationPack, $organization->client->sub_domain);

        if (!$res) $organizationPack->delete();

    }

    public function createInSham(Organization $organization, string $sub_domain)
    {
        $domain = env('APP_DOMAIN');
        $url = "https://{$sub_domain}-back.{$domain}/api/organization";

        $tariff = Tariff::find($organization->client->tariff_id);

        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($url, [
            'name' => $organization->name,
            'tariff_id' => $tariff->id,
            'user_count' => $tariff->user_count,
            'project_count' => $tariff->project_count,
            'b_organization_id' => $organization->id,
        ]);

        return $response->successful();
    }

    public function addPackInSham(OrganizationPack $organizationPack, string $sub_domain)
    {
        $domain = env('APP_DOMAIN');
        $url = 'https://' . $sub_domain . '-back.' . $domain . '/api/organization/add-pack';

        $organization = $organizationPack->organization()->first();
        $pack = $organizationPack->pack()->first();
        $data = [
            'type' => $pack->type,
            'b_organization_id' => $organization->id,
        ];

        if ($pack->type == 'user') {
            $data['user_count'] = $pack->amount;
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($url, $data);

        return $response->successful();
    }
}
