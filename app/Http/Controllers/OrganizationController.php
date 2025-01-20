<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organization\AccessRequest;
use App\Http\Requests\Organization\AddPackRequest;
use App\Http\Requests\Organization\StoreRequest;
use App\Http\Requests\Organization\UpdateRequest;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationPack;
use App\Models\Pack;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use Illuminate\Http\RedirectResponse;

class OrganizationController extends Controller
{
    public function __construct(public OrganizationRepositoryInterface $repository) { }

    public function store(Client $client, StoreRequest $request): RedirectResponse
    {
        $this->repository->store($client, $request->validated());

        return redirect()->back();
    }

    public function show(Organization $organization)
    {
        $packs = Pack::where('tariff_id', $organization->client?->tariff_id)->get();

        return view('admin.organizations.show', compact('organization', 'packs'));
    }

    public function update(Organization $organization, UpdateRequest $request): RedirectResponse
    {
        $this->repository->update($organization, $request->validated());

        return redirect()->back();
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $this->repository->destroy($organization);

        return redirect()->back();
    }

    public function access(AccessRequest $request)
    {
        $this->repository->access($request->validated());
    }

    public function addPack(Organization $organization, AddPackRequest $request)
    {
        $this->repository->addPack($organization, $request->validated());

        return redirect()->back();
    }

    public function deletePack(OrganizationPack $organizationPack)
    {
        $organizationPack->delete();

        return redirect()->back();
    }

}
