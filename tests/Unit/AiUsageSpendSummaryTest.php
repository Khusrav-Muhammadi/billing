<?php

namespace Tests\Unit;

use App\Services\Ai\AiUsageSpendSummary;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AiUsageSpendSummaryTest extends TestCase
{
    public function test_current_month_range_uses_dushanbe_calendar_month(): void
    {
        $now = Carbon::parse('2026-09-21 16:55:00', 'Asia/Dushanbe');
        $range = (new AiUsageSpendSummary())->currentMonthRange($now);

        $this->assertSame('2026-09-01 00:00:00', $range['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-30 23:59:59', $range['end']->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Dushanbe', $range['start']->timezoneName);
    }

    public function test_month_label_is_russian(): void
    {
        $now = Carbon::parse('2026-09-21 12:00:00', 'Asia/Dushanbe');

        $this->assertSame('сентябрь 2026', (new AiUsageSpendSummary())->monthLabel($now));
    }
}
