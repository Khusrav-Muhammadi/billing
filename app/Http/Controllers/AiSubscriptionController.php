<?php

namespace App\Http\Controllers;

use App\Models\Ai\AiBalance;
use App\Models\Ai\AiBalanceTransaction;
use App\Models\Ai\AiSubscription;
use App\Models\Ai\AiTariffPlan;
use App\Models\Ai\AiUsageLog;
use Illuminate\Http\Request;

class AiSubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = AiSubscription::query()
            ->with(['organization', 'plan', 'aiBalance'])
            ->select('ai_subscriptions.*');

        // ── Фильтры ──
        if ($request->filled('status')) {
            $query->where('status', (bool) $request->status);
        }

        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->whereHas('organization', function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('phone', 'like', $search);
            });
        }

        if ($request->filled('expires_before')) {
            $query->whereDate('expires_at', '<=', $request->expires_before);
        }

        // ── Сортировка ──
        $sort      = $request->input('sort', 'id');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'organization' => $query->leftJoin('organizations', 'organizations.id', '=', 'ai_subscriptions.organization_id')
                                    ->orderBy('organizations.name', $direction)
                                    ->select('ai_subscriptions.*'),
            'plan'         => $query->leftJoin('ai_tariff_plans', 'ai_tariff_plans.id', '=', 'ai_subscriptions.plan_id')
                                    ->orderBy('ai_tariff_plans.name', $direction)
                                    ->select('ai_subscriptions.*'),
            'period_months', 'status', 'started_at', 'expires_at'
                           => $query->orderBy('ai_subscriptions.' . $sort, $direction),
            default        => $query->orderBy('ai_subscriptions.id', $direction),
        };

        $subscriptions = $query->paginate(50)->withQueryString();
        $plans         = AiTariffPlan::query()->orderBy('name')->get();

        return view('admin.ai-subscriptions.index', compact('subscriptions', 'plans'));
    }

    public function show(AiSubscription $aiSubscription)
    {
        $aiSubscription->load(['organization', 'plan', 'commercialOffer']);

        $balance = AiBalance::query()
            ->where('organization_id', $aiSubscription->organization_id)
            ->with('currency')
            ->first();

        $transactions = AiBalanceTransaction::query()
            ->where('organization_id', $aiSubscription->organization_id)
            ->with('currency')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $usageLogs = AiUsageLog::query()
            ->where('organization_id', $aiSubscription->organization_id)
            ->with('currency')
            ->orderByDesc('period_start')
            ->limit(50)
            ->get();

        return view('admin.ai-subscriptions.show', compact(
            'aiSubscription',
            'balance',
            'transactions',
            'usageLogs'
        ));
    }
}
