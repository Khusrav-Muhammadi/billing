<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tariff\StoreRequest;
use App\Http\Requests\Tariff\UpdateRequest;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Tariff;
use App\Models\TariffCurrency;
use App\Support\CurrencyResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TariffController extends Controller
{
    public function index()
    {
        $tariffs = Tariff::query()
            ->with(['includedServices', 'excludedOrganizations'])
            ->orderBy('name')
            ->get();
        $baseTariffs = Tariff::query()
            ->where('is_tariff', true)
            ->where(function ($q) {
                $q->whereNull('is_extra_user')->orWhere('is_extra_user', false);
            })
            ->orderBy('name')
            ->get();

        $services = Tariff::query()
            ->where('is_tariff', false)
            ->where(function ($q) {
                $q->whereNull('is_extra_user')->orWhere('is_extra_user', false);
            })
            ->orderBy('name')
            ->get();

        $organizations = Organization::query()
            ->select(['id', 'name', 'order_number'])
            ->orderBy('name')
            ->get();

        return view('admin.tariffs.index', compact('tariffs', 'baseTariffs', 'services', 'organizations'));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        $data['price'] = (int) ($data['price'] ?? 0);

        // Checkbox fields: absent means "false"
        $data['is_tariff'] = (bool) ($data['is_tariff'] ?? false);
        $data['is_extra_user'] = (bool) ($data['is_extra_user'] ?? false);
        $data['can_increase'] = (bool) ($data['can_increase'] ?? false);

        if ($data['is_extra_user']) {
            $data['is_tariff'] = false; // extra user is not a tariff itself
        }

        if (!$data['is_extra_user']) {
            $data['parent_tariff_id'] = null;
        }

        $tariff = Tariff::create($data);

        $this->syncIncludedServices($tariff, $request);
        $this->syncExclusions($tariff, $request);

        return redirect()->route('tariff.index');
    }

    public function update(Tariff $tariff, UpdateRequest $request)
    {
        $data = $request->validated();
        $data['price'] = (int) ($data['price'] ?? $tariff->price ?? 0);

        $data['is_tariff'] = (bool) ($data['is_tariff'] ?? false);
        $data['is_extra_user'] = (bool) ($data['is_extra_user'] ?? false);
        $data['can_increase'] = (bool) ($data['can_increase'] ?? false);
        if ($data['is_extra_user']) {
            $data['is_tariff'] = false;
        }
        if (!$data['is_extra_user']) {
            $data['parent_tariff_id'] = null;
        }

        $tariff->update($data);

        $this->syncIncludedServices($tariff, $request);
        $this->syncExclusions($tariff, $request);

        return redirect()->route('tariff.index');
    }

    private function syncIncludedServices(Tariff $tariff, Request $request): void
    {
        // Only base tariffs can include services
        if (!$tariff->is_tariff || $tariff->is_extra_user) {
            $tariff->includedServices()->detach();
            return;
        }

        $ids = array_values(array_filter(array_map('intval', (array) $request->input('included_services', []))));
        if (!$ids) {
            $tariff->includedServices()->detach();
            return;
        }

        $qtyMap = (array) $request->input('included_services_qty', []);
        $servicesCanIncrease = Tariff::query()
            ->whereIn('id', $ids)
            ->pluck('can_increase', 'id')
            ->toArray();

        $sync = [];
        foreach ($ids as $id) {
            $qty = (int) ($qtyMap[$id] ?? 1);
            if ($qty < 1) $qty = 1;
            if (empty($servicesCanIncrease[$id])) {
                $qty = 1;
            }
            $sync[$id] = ['quantity' => $qty];
        }

        $tariff->includedServices()->sync($sync);
    }

    private function syncExclusions(Tariff $tariff, Request $request): void
    {
        // Exclusions are applied only for services (not tariffs and not extra-users).
        if ($tariff->is_tariff || $tariff->is_extra_user) {
            $tariff->excludedOrganizations()->detach();
            return;
        }

        $ids = array_values(array_filter(array_map('intval', (array) $request->input('excluded_organization_ids', []))));
        if (!$ids) {
            $tariff->excludedOrganizations()->detach();
            return;
        }

        $allowedIds = Organization::query()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $tariff->excludedOrganizations()->sync($allowedIds);
    }

    public function destroy(Tariff $tariff)
    {
        $tariff->delete();

        return redirect()->back();
    }

    public function includedServicesIndex(Tariff $tariff)
    {
        abort_if(!$tariff->is_tariff || $tariff->is_extra_user, 404);

        $tariff->load('includedServices');

        $services = Tariff::query()
            ->where('is_tariff', false)
            ->where(function ($q) {
                $q->whereNull('is_extra_user')->orWhere('is_extra_user', false);
            })
            ->orderBy('name')
            ->get();

        return view('admin.tariffs.included_services', compact('tariff', 'services'));
    }

    public function includedServicesStore(Tariff $tariff, Request $request)
    {
        abort_if(!$tariff->is_tariff || $tariff->is_extra_user, 404);

        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:tariffs,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $service = Tariff::query()->findOrFail((int) $data['service_id']);
        abort_if((bool) $service->is_tariff || (bool) $service->is_extra_user, 422);

        $qty = (int) ($data['quantity'] ?? 1);
        if ($qty < 1) $qty = 1;
        if (!(bool) ($service->can_increase ?? false)) {
            $qty = 1;
        }

        $tariff->includedServices()->syncWithoutDetaching([
            $service->id => ['quantity' => $qty],
        ]);

        return redirect()->route('tariff.included_services.index', $tariff);
    }

    public function includedServicesUpdate(Tariff $tariff, Tariff $service, Request $request)
    {
        abort_if(!$tariff->is_tariff || $tariff->is_extra_user, 404);
        abort_if((bool) $service->is_tariff || (bool) $service->is_extra_user, 404);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $qty = (int) $data['quantity'];
        if (!(bool) ($service->can_increase ?? false)) {
            $qty = 1;
        }

        $tariff->includedServices()->syncWithoutDetaching([
            $service->id => ['quantity' => $qty],
        ]);

        return redirect()->route('tariff.included_services.index', $tariff);
    }

    public function includedServicesDestroy(Tariff $tariff, Tariff $service)
    {
        abort_if(!$tariff->is_tariff || $tariff->is_extra_user, 404);
        abort_if((bool) $service->is_tariff || (bool) $service->is_extra_user, 404);

        $tariff->includedServices()->detach($service->id);

        return redirect()->route('tariff.included_services.index', $tariff);
    }

    public function getTariffByCurrency(Request $request)
    {
        $referer = $request->header('referer');
        $host = parse_url($referer, PHP_URL_HOST);

        $parts = explode('.', $host);
        $subdomain = count($parts) > 2 ? $parts[0] : null;
        $client = Client::query()
            ->with('country:id,currency_id')
            ->where('sub_domain', $subdomain)
            ->first();

        $currencyId = (int) ($client?->country?->currency_id ?? 0);
        $tariffs = TariffCurrency::query()
            ->when($currencyId > 0, fn ($q) => $q->where('currency_id', $currencyId))
            ->with('tariff')
            ->get();

        return response()->json($tariffs);
    }

    public function tariff(Request $request)
    {
        $currencyCode = ((int) $request->code === 998) ? 'UZS' : 'TJS';
        $currencyId = CurrencyResolver::idFromCode($currencyCode);

        $tariff = TariffCurrency::query()
            ->when($currencyId > 0, fn ($q) => $q->where('currency_id', $currencyId))
            ->with('tariff')
            ->get();

        return response()->json($tariff);

    }
}
