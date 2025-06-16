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
        $referer = $request->header('referer');
        $host = parse_url($referer, PHP_URL_HOST);

        $parts = explode('.', $host);
//        $subdomain = count($parts) > 2 ? $parts[0] : null;
        $subdomain = 'localhost';
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
