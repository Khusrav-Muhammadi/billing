<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImplementationDiscountCap\StoreRequest;
use App\Http\Requests\ImplementationDiscountCap\UpdateRequest;
use App\Models\ImplementationDiscountCap;
use App\Models\Tariff;
use Illuminate\Support\Facades\Schema;

class ImplementationDiscountCapController extends Controller
{
    private function resolveLegacyTariffId(): ?int
    {
        $id = Tariff::query()
            ->where('is_tariff', true)
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    public function index()
    {
        $caps = ImplementationDiscountCap::query()
            ->orderByDesc('id')
            ->get();

        return view('admin.implementation.discount_caps', compact('caps'));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = (bool)($data['is_active'] ?? false);

        $match = ['period_type' => $data['period_type']];
        $payload = [
            'max_percent' => $data['max_percent'],
            'is_active' => $data['is_active'],
        ];

        // Backward compatibility: old schema had required tariff_id.
        if (Schema::hasColumn('implementation_discount_caps', 'tariff_id')) {
            $legacyTariffId = $this->resolveLegacyTariffId();
            if (!$legacyTariffId) {
                abort(422, 'Не найден тариф для сохранения скидки (legacy schema).');
            }
            $match['tariff_id'] = $legacyTariffId;
            $payload['tariff_id'] = $legacyTariffId;
        }

        ImplementationDiscountCap::query()->updateOrCreate($match, $payload);

        return redirect()->back();
    }

    public function update(ImplementationDiscountCap $cap, UpdateRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = (bool)($data['is_active'] ?? false);

        $update = [
            'period_type' => $data['period_type'],
            'max_percent' => $data['max_percent'],
            'is_active' => $data['is_active'],
        ];

        if (Schema::hasColumn('implementation_discount_caps', 'tariff_id')) {
            $legacyTariffId = $this->resolveLegacyTariffId();
            if ($legacyTariffId) {
                $update['tariff_id'] = $legacyTariffId;
            }
        }

        $cap->update($update);

        return redirect()->back();
    }

    public function destroy(ImplementationDiscountCap $cap)
    {
        $cap->delete();

        return redirect()->back();
    }
}
