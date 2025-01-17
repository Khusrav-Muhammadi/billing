<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organization\AddPackRequest;
use App\Http\Requests\Organization\StoreRequest;
use App\Http\Requests\Organization\UpdateRequest;
use App\Jobs\AddPackJob;
use App\Jobs\DeleteOrganizationJob;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationPack;
use App\Models\Pack;
use App\Models\Tariff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class OrganizationController extends Controller
{

    public function store(Client $client, StoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['client_id'] = $client->id;

        $organization = Organization::create($data);

        $res = $this->createInSham($organization, $client->sub_domain);

        if (!$res) $organization->delete();

        return redirect()->back();
    }

    public function show(Organization $organization)
    {
        $packs = Pack::where('tariff_id', $organization->client?->tariff_id)->get();

        return view('admin.organizations.show', compact('organization', 'packs'));
    }

    public function update(Organization $organization, UpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $organization->update($data);

        return redirect()->back();
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        DeleteOrganizationJob::dispatch($organization, $organization->client->sub_domain);

        $organization->delete();

        return redirect()->back();
    }

    public function access(Request $request)
    {
        $data = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'client_id' => 'required',
            'has_access' => 'required',
        ]);

        $organization =  Organization::find($data['organization_id']);

        $organization->update([
            'has_access' => $data['has_access']
        ]);

        $client = Client::find($data['client_id']);


        $domain = env('APP_DOMAIN');
        Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
        ])->post("http://{$client->back_sub_domain}.{$domain}/api/organization/access/{$organization->id}", [
            'has_access' => $data['has_access']
        ]);

    }

    public function addPack(Organization $organization, AddPackRequest $request)
    {
        $data = $request->validated();

        $organizationPack = OrganizationPack::create([
            'organization_id' => $organization->id,
            'pack_id' => $data['pack_id'],
            'date' => $data['date'],
        ]);

        $res = $this->addPackInSham($organizationPack, $organization->client->sub_domain);

        if (!$res) $organizationPack->delete();

        return redirect()->back();
    }

    public function deletePack(OrganizationPack $organizationPack)
    {
        $organizationPack->delete();

        return redirect()->back();
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
            'lead_count' => $tariff->lead_count,
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
        } else {
            $data['lead_count'] = $pack->amount;
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($url, $data);

        return $response->successful();
    }
}
