<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\AddPackRequest;
use App\Http\Requests\Organization\LegalInfoRequest;
use App\Http\Requests\Organization\StoreRequest;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationPack;
use App\Models\Pack;
use App\Models\Transaction;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use App\Services\Sale\SaleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class OrganizationController extends Controller
{
    public function __construct(public OrganizationRepositoryInterface $repository) { }

    public function store(Client $client, StoreRequest $request): JsonResponse
    {
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
        $this->repository->access($organization, []);

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

    public function tariffInfo(Organization $organization)
    {
        $transaction = Transaction::where('organization_id', $organization->id)->first();

        $client = $organization->client;

        $tariffPrice = $client->tariffPrice;

        $currentMonth = Carbon::now();

        $daysInMonth = $currentMonth->daysInMonth;

        $sum = $tariffPrice->tariff_price / $daysInMonth;

        $days = $organization->balance / $sum;

        $endDate = Carbon::now()->addDays($days);

        return [
            'name' => $tariffPrice->tariff?->name,
            'start_date' => $transaction->created_at ?? '',
            'end_date' => $endDate,
            'users_count' => $tariffPrice->tariff->user_count,
            'price' => $tariffPrice->tariff_price,
            'balance' => $organization->balance,
            'license_price' => $tariffPrice->license_price,
            'id' => $tariffPrice->id,
            'country' => $client->country,
            'is_demo' => $client->is_demo,
        ];
    }

    public function getLegalInfo(Organization $organization)
    {
        return [
            'legal_name' => $organization->legal_name,
            'legal_legal_address' => $organization->legal_address,
            'legal_INN' => $organization->INN,
            'legal_phone' => $organization->phone,
            'legal_director' => $organization->director,
        ];
    }

    public function addLegalInfo(Organization $organization, LegalInfoRequest $request)
    {
        return $this->repository->addLegalInfo($organization, $request->validated());
    }

    public function addOrganization(StoreRequest $request)
    {
        $data = $request->validated();
        $data['sub_domain'] = parse_url($request->fullUrl(), PHP_URL_HOST);

        $organization = $this->repository->addOrganization($data);

        return [
            'organization' => $organization,
            'tariff_id' => $organization->client->tariff_id
        ];
    }

}
