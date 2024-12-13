<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organization\StoreRequest;
use App\Http\Requests\Organization\UpdateRequest;
use App\Jobs\OrganizationJob;
use App\Models\BusinessType;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Sale;
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

        OrganizationJob::dispatch($organization, $client->back_sub_domain);

        return redirect()->back();
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


        Http::withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
        ])->post("http://$client->back_sub_domain.shamcrm.com/api/organization/access/$organization->id",[
            'has_access' => $data['has_access']
        ]);
    }
}
