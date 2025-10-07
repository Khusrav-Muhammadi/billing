<?php

namespace App\Repositories;

use App\Jobs\ActivationJob;
use App\Jobs\CreateOrganizationJob;
use App\Jobs\SendOrganizationLicense;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationPack;
use App\Models\Transaction;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OrganizationRepository implements OrganizationRepositoryInterface
{
    public function store(Client $client, array $data)
    {
        $data['client_id'] = $client->id;

        return DB::transaction(function () use ($client, $data) {
            $organization = Organization::create($data);

            $password = Str::random(12);

            $this->createInSham($organization, $client, $password);
            $res = true;
            if (!$res) $organization->delete();
            else {
                $daysInMonth = Carbon::now()->daysInMonth;

                $sum = $client->tariffPrice->tariff_price / $daysInMonth;

                if (!$client->is_demo) {
                    $currency = $client->currency;

                    $accountedAmount = $currency->symbol_code == 'USD' ? $sum / $currency->latestExchangeRate->kurs : $sum;

                    Transaction::create([
                        'client_id' => $client->id,
                        'organization_id' => $organization->id,
                        'tariff_id' => $client->tariff->id,
                        'sale_id' => $client->sale?->id,
                        'sum' => $sum,
                        'type' => 'Снятие',
                        'accounted_amount' => $accountedAmount

                    ]);
                    if ($client->tariff->id == 1) {
                        $sum = 499;
                    } elseif ($client->tariff->id == 2) {
                        $sum = 1299;
                    } else {
                        $sum = 2599;
                    }

                    $currency = $client->currency;

                    $accountedAmount = $currency->symbol_code == 'USD' ? $sum / $currency->latestExchangeRate->kurs : $sum;

                    Transaction::create([
                        'client_id' => $client->id,
                        'organization_id' => $organization->id,
                        'tariff_id' => $client->tariff->id,
                        'sale_id' => $client->sale?->id,
                        'sum' => $sum,
                        'type' => 'Снятие',
                        'accounted_amount' => $accountedAmount
                    ]);
                }
            }

            if (!$client->is_demo) {
                SendOrganizationLicense::dispatch($organization);
            }

            return $organization;
        });
    }

    public function update(Organization $organization, array $data)
    {
        $organization->update($data);
    }

    public function access(Organization $organization, array $data)
    {
        $client = $organization->client()->first();

        $reject_cause = $data['reject_cause'] ?? '';

        ActivationJob::dispatch(array($organization->id), $client->sub_domain, !$organization->has_access, false, auth()->id(), $reject_cause);
    }

    public function addPack(Organization $organization, array $data)
    {
        $organizationPack = OrganizationPack::create([
            'organization_id' => $organization->id,
            'pack_id' => $data['pack_id'],
            'date' => $data['date'],
            'amount' => $data['amount']
        ]);

        $res = $this->addPackInSham($organizationPack, $organization->client->sub_domain);

        if (!$res) $organizationPack->delete();

    }

    public function createInSham(Organization $organization, Client $client, string $password)
    {
        CreateOrganizationJob::dispatch($client, $organization, $password)->delay(120);
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
            $data['user_count'] = $organizationPack->amount;
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($url, $data);

        return $response->successful();
    }

    public function addLegalInfo(Organization $organization, array $data)
    {
        $organization->update([
            'legal_name' => $data['legal_name'] ?? $organization->legal_name,
            'legal_address' => $data['legal_address'] ?? $organization->legal_address,
            'INN' => $data['INN'] ?? $organization->INN,
            'phone' => $data['phone'] ?? $organization->phone,
            'director' => $data['director'] ?? $organization->director,
        ]);
    }

    public function addOrganization(array $data)
    {
        $client = Client::where('sub_domain', $data['sub_domain'])->first();

        return Organization::create([
            'client_id' => $client->id,
            'name' => $data['name'],
            'phone' => $data['phone']
        ]);
    }

}
