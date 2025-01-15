<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sale\StoreRequest;
use App\Http\Requests\Sale\UpdateRequest;
use App\Models\Sale;

class SaleController extends Controller
{
    public function index()
    {
        $sales = Sale::all();

        return view('admin.sales.index', compact('sales'));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        Sale::create($data);

        return redirect()->route('sale.index');
    }

    public function update(Sale $sale, UpdateRequest $request)
    {
        $data = $request->validated();

        $sale->update([
            'name' => $data['name'],
            'sale_type' => $data['sale_type'],
            'amount' => $data['amount'],
            'active' => isset($data['active']),
        ]);

        return redirect()->route('sale.index');
    }

    public function destroy(Sale $sale)
    {
        $sale->delete();

        return redirect()->back();
    }
}
