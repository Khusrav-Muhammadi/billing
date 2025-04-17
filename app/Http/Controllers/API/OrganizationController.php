<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\AccessRequest;
use App\Http\Requests\Organization\AddPackRequest;
use App\Models\BusinessType;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationPack;
use App\Models\Pack;
use App\Models\Partner;
use App\Models\Sale;
use App\Models\Tariff;
use App\Models\Transaction;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(public OrganizationRepositoryInterface $repository) { }

    public function store(Client $client, \App\Http\Requests\Organization\StoreRequest $request): \Illuminate\Http\JsonResponse
    {
        dd($client);
        $organization = $this->repository->store($client, $request->validated());

        if (!$organization) {
            return response()->json([
                'error' => 'Не удалось создать организацию, попробуйте позже'
            ], 400);
        }

        return response()->json(['success'=> true]);
    }

    public function show(Organization $organization)
    {
        $packs = Pack::where('tariff_id', $organization->client?->tariff_id)->with('tariff')->get();

        return response()->json(['success'=> true, 'packs' => $packs, 'organization' => $organization->load('packs.pack', 'businessType')]);
    }

    public function update(Organization $organization, \App\Http\Requests\Organization\UpdateRequest $request)
    {
        $this->repository->update($organization, $request->validated());

        return response()->json(['success'=> true]);
    }

    public function destroy(Organization $organization)
    {
        $this->repository->destroy($organization);

        return response()->json(['success'=> true]);
    }

    public function access(Organization $organization)
    {
        $this->repository->access($organization);

        return response()->json(['success'=> true]);
    }

    public function addPack(int $id, AddPackRequest $request)
    {
        $organization = Organization::find($id);
        $this->repository->addPack($organization, $request->validated());

        return response()->json(['success'=> true]);

    }

    public function deletePack(int $id)
    {
        OrganizationPack::where('id', $id)->delete();

        return response()->json(['success'=> true]);
    }

}
