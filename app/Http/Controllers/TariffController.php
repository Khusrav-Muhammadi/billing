<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tariff\StoreRequest;
use App\Http\Requests\Tariff\UpdateRequest;
use App\Models\Client;
use App\Models\Tariff;
use App\Models\TariffCurrency;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TariffController extends Controller
{
    public function index()
    {
        $tariffs = Tariff::query()->orderBy('name')->get();
        $baseTariffs = Tariff::query()
            ->where('is_tariff', true)
            ->where(function ($q) {
                $q->whereNull('is_extra_user')->orWhere('is_extra_user', false);
            })
            ->orderBy('name')
            ->get();

        return view('admin.tariffs.index', compact('tariffs', 'baseTariffs'));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        // Checkbox fields: absent means "false"
        $data['is_tariff'] = (bool) ($data['is_tariff'] ?? false);
        $data['is_extra_user'] = (bool) ($data['is_extra_user'] ?? false);

        if ($data['is_extra_user']) {
            $data['is_tariff'] = false; // extra user is not a tariff itself
        }

        if (!$data['is_extra_user']) {
            $data['parent_tariff_id'] = null;
        }

        Tariff::create($data);

        return redirect()->route('tariff.index');
    }

    public function update(Tariff $tariff, UpdateRequest $request)
    {
        $data = $request->validated();

        $data['is_tariff'] = (bool) ($data['is_tariff'] ?? false);
        $data['is_extra_user'] = (bool) ($data['is_extra_user'] ?? false);
        if ($data['is_extra_user']) {
            $data['is_tariff'] = false;
        }
        if (!$data['is_extra_user']) {
            $data['parent_tariff_id'] = null;
        }

        $tariff->update($data);

        return redirect()->route('tariff.index');
    }

    public function destroy(Tariff $tariff)
    {
        $tariff->delete();

        return redirect()->back();
    }

    public function getTariffByCurrency(Request $request)
    {
        $referer = $request->header('referer');
        $host = parse_url($referer, PHP_URL_HOST);

        $parts = explode('.', $host);
        $subdomain = count($parts) > 2 ? $parts[0] : null;
        $client = Client::query()->where('sub_domain', $subdomain)->first();

        $tariffs = TariffCurrency::query()->where('currency_id', $client->currency_id)->with('tariff')->get();

        return response()->json($tariffs);
    }

    public function tariff(Request $request)
    {
        if ($request->code == 998) {
            $tariff = TariffCurrency::query()->where('currency_id', 2)->with('tariff')->get();
        } else {
            $tariff = TariffCurrency::query()->where('currency_id', 1)->with('tariff')->get();
        }

        return response()->json($tariff);

    }
}
