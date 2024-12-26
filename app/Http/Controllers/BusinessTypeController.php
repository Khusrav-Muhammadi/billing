<?php

namespace App\Http\Controllers;

use App\Http\Requests\BusinessType\StoreRequest;
use App\Http\Requests\BusinessType\UpdateRequest;
use App\Models\BusinessType;

class BusinessTypeController extends Controller
{
    public function index()
    {
        $businessTypes = BusinessType::all();

        return view('admin.business_types.index', compact('businessTypes'));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        BusinessType::create($data);

        return redirect()->route('business_type.index');
    }

    public function update(BusinessType $businessType, UpdateRequest $request)
    {
        $data = $request->validated();

        $businessType->update($data);

        return redirect()->route('business_type.index');
    }

    public function destroy(BusinessType $businessType)
    {
        $businessType->delete();

        return redirect()->back();
    }
}
