<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ClientHealth\ClientHealthReadService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientActivityController extends Controller
{
    public function __construct(private readonly ClientHealthReadService $health)
    {
    }

    public function index(Request $request): View
    {
        $type = in_array($request->query('type'), ['paid', 'demo'], true) ? $request->query('type') : null;
        $days = in_array((int) $request->query('days'), [14, 30, 60], true) ? (int) $request->query('days') : 30;

        $series = $this->health->activitySeries($type, $days);
        $latest = $series === [] ? null : $series[array_key_last($series)];

        return view('admin.activity.index', [
            'series' => $series,
            'latest' => $latest,
            'type' => $type,
            'days' => $days,
        ]);
    }
}
