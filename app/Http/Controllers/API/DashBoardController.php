<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Tariff;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashBoardController extends Controller
{
    public function index(Request $request)
    {
        $year = 2025;

        $clients = Client::query()
            ->where('partner_id', auth()->id())
            ->selectRaw('
        SUM(CASE WHEN clients.is_demo = 0 THEN 1 ELSE 0 END) as real_clients,
        SUM(CASE WHEN clients.is_demo = 1 THEN 1 ELSE 0 END) as demo_clients
    ')
            ->whereYear('created_at', $year)
            ->first();

        $clientsActivity = Client::query()
            ->where('partner_id', auth()->id())
            ->selectRaw('
        DATE_FORMAT(created_at, "%Y-%m") as month,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_clients,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_clients
    ')
            ->whereYear('created_at', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $activeClientsByMonth = array_fill(0, 12, 0);
        $inactiveClientsByMonth = array_fill(0, 12, 0);

        foreach ($clientsActivity as $activity) {
            $month = (int)date('m', strtotime($activity->month)) - 1;
            $activeClientsByMonth[$month] = (int)$activity->active_clients;
            $inactiveClientsByMonth[$month] = (int)$activity->inactive_clients;
        }

        $clients_count = Client::query()
            ->where([
                ['is_active', 1],
                ['partner_id', auth()->id()]
            ])
            ->count();

        $totalIncomeForMonth = 0;
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $clientsss = Client::whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->where([
                ['is_active', 1],
                ['partner_id', auth()->id()]
            ])
            ->get();

        $organizationIds = [];

        foreach ($clientsss as $client) {
            $tariff = Tariff::find($client->tariff_id);
            if ($tariff) {
                $totalIncomeForMonth += $tariff->price;
            }

            $organizationIds = array_merge($organizationIds, $client->organizations()->pluck('id')->toArray());
        }


        $results = DB::table('transactions')
            ->join('tariffs', 'transactions.tariff_id', '=', 'tariffs.id')
            ->select('tariffs.name', DB::raw('SUM(transactions.sum) as total'), DB::raw('MONTH(transactions.created_at) as month'))
            ->where('transactions.type', 'Снятие')
            ->whereYear('transactions.created_at', $year)
            ->whereIn('organization_id', $organizationIds)
            ->groupBy('tariffs.name', 'month')
            ->orderBy('tariffs.name')
            ->get();

        $formattedResults = [];

        foreach ($results as $result) {
            if (!isset($formattedResults[$result->name])) {
                $formattedResults[$result->name] = [
                    'name' => $result->name,
                    'data' => array_fill(0, 12, 0),
                ];
            }

            $formattedResults[$result->name]['data'][$result->month - 1] = (int)$result->total;
        }

        $chartData = array_values($formattedResults);

        return response()->json([
            'clients' => $clients,
            'activeClientsByMonth' => $activeClientsByMonth,
            'inactiveClientsByMonth' => $inactiveClientsByMonth,
            'chartData' => $chartData,
            'clients_count' => $clients_count,
            'totalIncomeForMonth' => $totalIncomeForMonth,
        ]);
    }

}
