<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\AddPackRequest;
use App\Http\Requests\Organization\RejectRequest;
use App\Http\Requests\Organization\StoreRequest;
use App\Http\Requests\Organization\UpdateRequest;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationPack;
use App\Models\Pack;
use App\Models\Tariff;
use App\Models\User;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationV2Controller extends Controller
{
    public function __construct(public OrganizationRepositoryInterface $repository) { }

    public function index(Request $request)
    {
        $organizations = $this->repository->index($request->all());

        $partners = User::query()->where('role', 'partner')->get();
        $tariffs = Tariff::all();

        if ($request->ajax()) {
            return view('admin.partials.organizations_v2', compact('organizations'))->render();
        }

        return view('v2.organizations_v2.index', compact('organizations', 'partners', 'tariffs'));
    }

    public function demo(Request $request)
    {
        $organizations = $this->repository->demo($request->all());

        $partners = User::query()->where('role', 'partner')->get();
        $tariffs = Tariff::all();

        if ($request->ajax()) {
            return view('admin.partials.organizations_v2', compact('organizations'))->render();
        }

        return view('v2.organizations_v2.demo', compact('organizations', 'partners', 'tariffs'));
    }

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
        $userCount = $organization->client()->first()->tariff->user_count;

        return view('admin.organizations.show', compact('organization', 'packs', 'userCount'));
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

    public function access(Organization $organization, RejectRequest $request)
    {
        $this->repository->access($organization, $request->validated());

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
