<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ai\AiBalance;
use App\Models\Ai\AiBalanceTransaction;
use App\Models\Ai\AiSubscription;
use App\Models\Ai\AiUsageLog;
use App\Models\Ai\AiUsageRawLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $query = AiSubscription::query()
            ->with([
                'organization:id,name,phone,email,order_number',
                'plan:id,name',
                'aiBalance:id,organization_id,limited_balance,ai_balance,is_agent_enabled,currency_id,scheduled_activation_at',
                'aiBalance.currency:id,symbol_code,name',
            ])
            ->select('ai_subscriptions.*');

        if ($request->filled('status')) {
            $query->where('status', filter_var($request->query('status'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('plan_id')) {
            $query->where('plan_id', (int) $request->query('plan_id'));
        }

        if ($request->filled('organization_id')) {
            $query->where('organization_id', (int) $request->query('organization_id'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->query('search')) . '%';
            $query->whereHas('organization', function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('phone', 'like', $search)
                    ->orWhere('order_number', 'like', $search);
            });
        }

        if ($request->filled('expires_before')) {
            $query->whereDate('expires_at', '<=', $request->query('expires_before'));
        }

        if ($request->filled('expires_after')) {
            $query->whereDate('expires_at', '>=', $request->query('expires_after'));
        }

        $sort = (string) $request->input('sort', 'id');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'organization' => $query->leftJoin('organizations', 'organizations.id', '=', 'ai_subscriptions.organization_id')
                ->orderBy('organizations.name', $direction)
                ->select('ai_subscriptions.*'),
            'plan' => $query->leftJoin('ai_tariff_plans', 'ai_tariff_plans.id', '=', 'ai_subscriptions.plan_id')
                ->orderBy('ai_tariff_plans.name', $direction)
                ->select('ai_subscriptions.*'),
            'period_months', 'status', 'started_at', 'expires_at', 'price_paid'
                => $query->orderBy('ai_subscriptions.' . $sort, $direction),
            default => $query->orderBy('ai_subscriptions.id', $direction),
        };

        $subscriptions = $query->paginate($perPage)->withQueryString();

        return response()->json($subscriptions);
    }

    public function show(AiSubscription $aiSubscription): JsonResponse
    {
        $aiSubscription->load([
            'organization:id,name,phone,email,order_number',
            'plan:id,name',
            'commercialOffer:id,request_type,status,grand_total,currency,partner_id,organization_id',
        ]);

        $balance = AiBalance::query()
            ->where('organization_id', $aiSubscription->organization_id)
            ->with('currency:id,symbol_code,name')
            ->first();

        $transactions = AiBalanceTransaction::query()
            ->where('organization_id', $aiSubscription->organization_id)
            ->with('currency:id,symbol_code,name')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $usageLogs = AiUsageLog::query()
            ->where('organization_id', $aiSubscription->organization_id)
            ->with(['currency:id,symbol_code,name', 'rawLogs.costCurrency:id,symbol_code,name'])
            ->orderByDesc('period_start')
            ->limit(50)
            ->get();

        $this->attachFallbackRawLogs($usageLogs, (int) $aiSubscription->organization_id);

        return response()->json([
            'subscription' => $aiSubscription,
            'balance' => $balance,
            'transactions' => $transactions,
            'usage_logs' => $usageLogs,
        ]);
    }

    /**
     * Для старых 30-мин циклов без ai_usage_log_id — подтягиваем сырые логи по времени периода.
     */
    private function attachFallbackRawLogs($usageLogs, int $orgId): void
    {
        $needsFallback = $usageLogs->filter(fn (AiUsageLog $u) => $u->rawLogs->isEmpty());
        if ($needsFallback->isEmpty()) {
            return;
        }

        $minStart = $needsFallback->min('period_start');
        $maxEnd = $needsFallback->max('period_end');
        if (! $minStart || ! $maxEnd) {
            return;
        }

        $orphanRaws = AiUsageRawLog::query()
            ->where('organization_id', $orgId)
            ->where('processed', true)
            ->whereNull('ai_usage_log_id')
            ->where(function ($q) use ($minStart, $maxEnd) {
                $q->whereBetween('created_at', [$minStart, $maxEnd])
                    ->orWhereBetween('fetched_at', [$minStart, $maxEnd]);
            })
            ->with('costCurrency:id,symbol_code,name')
            ->orderBy('created_at')
            ->get();

        foreach ($needsFallback as $usageLog) {
            $matched = $orphanRaws->filter(function (AiUsageRawLog $raw) use ($usageLog) {
                $start = $usageLog->period_start;
                $end = $usageLog->period_end;
                if (! $start || ! $end) {
                    return false;
                }

                $inCreated = $raw->created_at && $raw->created_at >= $start && $raw->created_at <= $end;
                $inFetched = $raw->fetched_at && $raw->fetched_at >= $start && $raw->fetched_at <= $end;

                return $inCreated || $inFetched;
            })->values();

            $usageLog->setRelation('rawLogs', $matched);
        }
    }
}
