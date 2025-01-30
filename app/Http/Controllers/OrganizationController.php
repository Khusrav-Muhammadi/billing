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
        $organization = $this->repository->store($client, $request->validated());

        if (!$organization) {
            return redirect()->back()->with('error', 'Не удалось создать организацию');
        }

        return redirect()->back();
    }

    public function show(Organization $organization)
    {
        $packs = Pack::where('tariff_id', $organization->client?->tariff_id)->get();
        $organization = $organization->load(['history.changes', 'history.user']);

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

    public function access(Organization $organization)
    {
        $this->repository->access($organization);

        return redirect()->back();
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
