<?php

namespace App\Http\Controllers;

use App\Http\Requests\Partner\StoreRequest;
use App\Models\Partner;

class ApplicationController extends Controller
{
    public function index()
    {

        return view('admin.applications.index');
    }

    public function create()
    {
        return view('admin.applications.create');
    }

    public function store(StoreRequest $request)
    {
        return redirect()->route('application.index');
    }


    public function edit(int $id)
    {
        return view('admin.applications.edit');
    }

    public function update(int $id, StoreRequest $request)
    {

        return redirect()->route('application.index');
    }

    public function destroy(Partner $partner)
    {
        $partner->delete();

        return redirect()->back();
    }

}
