<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tariff\StoreRequest;
use App\Http\Requests\Tariff\UpdateRequest;
use App\Models\Tariff;

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
}
