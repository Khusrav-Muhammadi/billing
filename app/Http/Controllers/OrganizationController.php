<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organization\AddPackRequest;
use App\Http\Requests\Organization\StoreRequest;
use App\Http\Requests\Organization\UpdateRequest;
use App\Jobs\AddPackJob;
use App\Jobs\OrganizationJob;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationPack;
use App\Models\Pack;
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

        OrganizationJob::dispatch($organization, $client->sub_domain);

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

        AddPackJob::dispatch($organizationPack, $organization->client->sub_domain);
        return redirect()->back();
    }

    public function deletePack(OrganizationPack $organizationPack)
    {
        $organizationPack->delete();

        return redirect()->back();
    }
}
