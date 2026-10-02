<?php

namespace Tests\Unit;

use App\Models\Ai\AiTariffPlan;
use App\Services\Ai\CallAnalyseQuotaResolver;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CallAnalyseQuotaResolverTest extends TestCase
{
    public function test_known_plan_ids_keep_fixed_daily_minutes(): void
    {
        $this->assertSame(30, AiTariffPlan::CALL_ANALYSE_DAILY_MINUTES[9]);
        $this->assertSame(60, AiTariffPlan::CALL_ANALYSE_DAILY_MINUTES[10]);
        $this->assertSame(180, AiTariffPlan::CALL_ANALYSE_DAILY_MINUTES[11]);
    }

    public function test_stored_daily_minutes_override_id_defaults(): void
    {
        $plan = new AiTariffPlan(['daily_minutes' => 45]);
        $plan->id = 9;

        $this->assertSame(45, $plan->resolvedDailyMinutes());
    }

    public function test_empty_stored_minutes_fall_back_to_plan_id(): void
    {
        $plan = new AiTariffPlan(['daily_minutes' => null]);
        $plan->id = 9;

        $this->assertSame(30, $plan->resolvedDailyMinutes());
    }

    public function test_empty_list_has_no_winner(): void
    {
        $this->assertNull(CallAnalyseQuotaResolver::pickBest([]));
    }

    public function test_zero_minutes_are_ignored(): void
    {
        $this->assertNull(CallAnalyseQuotaResolver::pickBest([
            ['plan_id' => 9, 'daily_minutes' => 0, 'expires_at' => now()],
        ]));
    }

    public function test_larger_daily_limit_wins(): void
    {
        $best = CallAnalyseQuotaResolver::pickBest([
            ['plan_id' => 9, 'daily_minutes' => 30, 'expires_at' => Carbon::parse('2026-12-01')],
            ['plan_id' => 11, 'daily_minutes' => 180, 'expires_at' => Carbon::parse('2026-10-01')],
        ]);

        $this->assertSame(11, $best['plan_id']);
        $this->assertSame(180, $best['daily_minutes']);
    }

    public function test_equal_minutes_prefer_later_expiry(): void
    {
        $best = CallAnalyseQuotaResolver::pickBest([
            ['plan_id' => 10, 'daily_minutes' => 60, 'expires_at' => Carbon::parse('2026-10-01')],
            ['plan_id' => 10, 'daily_minutes' => 60, 'expires_at' => Carbon::parse('2026-12-01')],
        ]);

        $this->assertSame('2026-12-01', $best['expires_at']->toDateString());
    }
}
