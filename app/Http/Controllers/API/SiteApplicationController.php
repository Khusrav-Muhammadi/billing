<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\NewSiteRequestJob;
use App\Models\SiteApplications;
use App\Models\Tariff;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Http\Request;

class SiteApplicationController extends Controller
{
    public function index()
    {
        $applications = SiteApplications::all();
        return view('admin.site-applications.index', compact('applications'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'fio' => 'required',
            'phone' => 'required',
            'email' => ['nullable', 'email'],
            'organization' => 'nullable',
            'region' => 'nullable',
            'request_type' => 'required',
        ]);

        SiteApplications::create($data);

        NewSiteRequestJob::dispatch(User::first(), $data['request_type']);
        return response()->json([
            'success' => true,
        ]);
    }

    public function destroy(SiteApplications $siteApplication)
    {
        $siteApplication->delete();

        return redirect()->back()->with('success', 'Успешно удалено');
    }

}
