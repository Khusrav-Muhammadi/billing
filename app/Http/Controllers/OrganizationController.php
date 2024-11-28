<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organization\StoreRequest;
use App\Http\Requests\Organization\UpdateRequest;
use App\Models\BusinessType;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Sale;

class OrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::all();

        return view('admin.organizations.index', compact('organizations'));
    }

    public function create()
    {
        $clients = Client::all();
        $sales= Sale::all();

        return view('admin.organizations.create', compact('clients', 'sales'));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        Organization::create($data);

        return redirect()->route('organization.index');
    }

    public function edit(Organization $organization)
    {
        $clients = Client::all();
        $sales= Sale::all();

        return view('admin.organizations.edit', compact('organization', 'clients', 'sales'));
    }

    public function update(Organization $organization, UpdateRequest $request)
    {
        $data = $request->validated();

        $organization->update($data);

        return redirect()->route('organization.index');
    }

    public function destroy(Organization $organization)
    {
        $organization->delete();

        return redirect()->back();
    }
}
