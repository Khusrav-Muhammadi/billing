<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tariff\StoreRequest;
use App\Http\Requests\Tariff\UpdateRequest;
use App\Models\Client;
use App\Models\Tariff;
use App\Models\TariffCurrency;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TariffController extends Controller
{
    public function index()
    {
        $tariffs = Tariff::all();

        return view('admin.tariffs.index', compact('tariffs'));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        Tariff::create($data);

        return redirect()->route('tariff.index');
    }

    public function update(Tariff $tariff, UpdateRequest $request)
    {
        $data = $request->validated();

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
        $subdomain = parse_url($request->fullUrl(), PHP_URL_HOST);

        $client = Client::query()->where('sub_domain', $subdomain)->first();

        $tariffs = TariffCurrency::query()->where('currency_id', $client->currency_id)->with('tariff')->get();

        return response()->json($tariffs);
    }
}
