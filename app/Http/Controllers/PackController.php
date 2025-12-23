<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pack\StoreRequest;
use App\Models\Pack;
use App\Models\Tariff;
use Illuminate\Support\Facades\Log;

class PackController extends Controller
{
    public function index()
    {
        $packs = Pack::all();
        $tariffs = Tariff::all();

        return view('admin.packs.index', compact('packs', 'tariffs'));
    }

    public function update(Pack $pack, StoreRequest $request)
    {
        $data = $request->validated();
        $pack->update([
            'name' => $data['name'],
            'amount' => $data['amount'],
            'price' => $data['price'],
            'payment_type' => $data['payment_type'],
            'is_external' => isset($data['is_external']),
        ]);

        return redirect()->route('pack.index');
    }

    public function destroy(Pack $pack)
    {
        $pack->delete();

        return redirect()->back();
    }
}
