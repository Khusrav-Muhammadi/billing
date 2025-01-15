<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pack\StoreRequest;
use App\Models\Pack;
use App\Models\Tariff;

class PackController extends Controller
{
    public function index()
    {
        $packs = Pack::all();
        $tariffs = Tariff::all();

        return view('admin.packs.index', compact('packs', 'tariffs'));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        Pack::create($data);

        return redirect()->route('pack.index');
    }

    public function update(Pack $pack, StoreRequest $request)
    {
        $data = $request->validated();

        $pack->update([
            'name' => $data['name'],
            'pack_type' => $data['pack_type'],
            'amount' => $data['amount'],
            'active' => isset($data['active']),
        ]);

        return redirect()->route('pack.index');
    }

    public function destroy(Pack $pack)
    {
        $pack->delete();

        return redirect()->back();
    }
}
