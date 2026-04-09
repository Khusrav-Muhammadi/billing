<?php

namespace App\Http\Controllers;

use App\Jobs\CreateDayClosingDocumentsJob;
use App\Models\DayClosing;
use App\Models\DayClosingDetail;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DayClosingController extends Controller
{
    public function index()
    {
        $dayClosings = DayClosing::query()
            ->with('author:id,name')
            ->withCount('details')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.day_closings.index', compact('dayClosings'));
    }

    public function create()
    {
        return view('admin.day_closings.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        CreateDayClosingDocumentsJob::dispatch(
            Carbon::parse($validated['date_from'])->toDateString(),
            Carbon::parse($validated['date_to'])->toDateString(),
            (int) Auth::id()
        );

        return redirect()
            ->route('day-closing.index')
            ->with('success', 'Создание закрытия дня запущено');
    }

    public function show(DayClosing $dayClosing)
    {
        $dayClosing->load([
            'author:id,name',
            'details' => function ($query) {
                $query->orderBy('id');
            },
            'details.organization:id,name,client_id',
            'details.currency:id,name,symbol_code',
            'details.serviceDetails' => function ($query) {
                $query->orderBy('id');
            },
            'details.serviceDetails.client:id,name',
            'details.serviceDetails.tariff:id,name',
        ]);

        $serviceRows = $dayClosing->details
            ->flatMap(function (DayClosingDetail $detail) {
                return $detail->serviceDetails->map(function ($serviceDetail) use ($detail) {
                    return [
                        'organization_name' => $detail->organization?->name ?: ($serviceDetail->client?->name ?? '-'),
                        'service_name' => $serviceDetail->tariff?->name ?? '-',
                        'monthly_sum' => (float) $serviceDetail->monthly_sum,
                        'daily_sum' => (float) $serviceDetail->daily_sum,
                    ];
                });
            })
            ->values();

        return view('admin.day_closings.show', compact('dayClosing', 'serviceRows'));
    }
}
