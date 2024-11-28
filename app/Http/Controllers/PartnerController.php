<?php

namespace App\Http\Controllers;

use App\Http\Requests\Partner\StoreRequest;
use App\Http\Requests\Partner\UpdateRequest;
use App\Models\Partner;
use App\Models\User;

class PartnerController extends Controller
{
    public function index()
    {
        $partners = Partner::all();

        return view('admin.partners.index', compact('partners'));
    }

    public function create()
    {
        $managers = User::all();

        return view('admin.partners.create', compact('managers'));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        Partner::create($data);

        return redirect()->route('partner.index');
    }

    public function edit(Partner $partner)
    {
        $managers = User::all();

        return view('admin.partners.edit', compact('partner', 'managers'));
    }

    public function update(Partner $partner, UpdateRequest $request)
    {
        $data = $request->validated();

        $partner->update($data);

        return redirect()->route('partner.index');
    }

    public function destroy(Partner $partner)
    {
        $partner->delete();

        return redirect()->back();
    }
}
