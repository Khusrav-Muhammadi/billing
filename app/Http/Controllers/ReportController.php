<?php

namespace App\Http\Controllers;

use App\Http\Requests\Client\GetBalanceRequest;
use App\Http\Requests\Client\StoreRequest;
use App\Http\Requests\Client\TransactionRequest;
use App\Http\Requests\Client\UpdateRequest;
use App\Models\BusinessType;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Pack;
use App\Models\Partner;
use App\Models\Sale;
use App\Models\Tariff;
use App\Models\Transaction;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function income()
    {
        setlocale(LC_TIME, 'ru_RU.UTF-8');
        Carbon::setLocale('ru');

        $months = collect(range(1, 12))->mapWithKeys(function ($month) {
            return [str_pad($month, 2, '0', STR_PAD_LEFT) => [
                'month' => Carbon::create()->month($month)->translatedFormat('F'),
                'total' => 0
            ]];
        });

        $incomeReport = DB::table('transactions as t')
            ->selectRaw('YEAR(t.created_at) as year, MONTH(t.created_at) as month, tariffs.name as tariff_name, SUM(t.sum) as total_income')
            ->join('tariffs', 't.tariff_id', '=', 'tariffs.id')
            ->where('t.type', 'Снятие')
            ->groupByRaw('YEAR(t.created_at), MONTH(t.created_at), tariffs.name')
            ->orderByRaw('YEAR(t.created_at), MONTH(t.created_at)')
            ->get();

        $report = $months->toArray();

        foreach ($incomeReport as $row) {
            $monthKey = str_pad($row->month, 2, '0', STR_PAD_LEFT);
            $monthName = Carbon::create()->month($row->month)->translatedFormat('F'); // Название месяца на русском

            if (!isset($report[$monthKey])) {
                $report[$monthKey] = [
                    'month' => $monthName,
                    'total' => 0
                ];
            }

            $report[$monthKey][$row->tariff_name] = $row->total_income;
            $report[$monthKey]['total'] += $row->total_income;
        }

        $incomes = array_values($report);

        return view('admin.report.income', compact('incomes'));
    }
}
