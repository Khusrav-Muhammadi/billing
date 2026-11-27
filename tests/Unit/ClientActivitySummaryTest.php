<?php

namespace Tests\Unit;

use App\Services\ClientHealth\ClientActivitySummary;
use PHPUnit\Framework\TestCase;

class ClientActivitySummaryTest extends TestCase
{
    public function test_working_share_is_green_and_yellow_of_everyone(): void
    {
        $series = (new ClientActivitySummary())->series([
            ['date' => '2026-11-24', 'color' => 'green', 'count' => 50],
            ['date' => '2026-11-24', 'color' => 'yellow', 'count' => 14],
            ['date' => '2026-11-24', 'color' => 'grey', 'count' => 16],
            ['date' => '2026-11-24', 'color' => 'red', 'count' => 20],
        ]);

        $this->assertCount(1, $series);
        $this->assertSame(100, $series[0]['total']);
        $this->assertSame(64, $series[0]['working']);
        $this->assertSame(64.0, $series[0]['percent']);
        $this->assertNull($series[0]['delta']);
        $this->assertNull($series[0]['direction']);
    }

    public function test_day_over_day_direction(): void
    {
        $series = (new ClientActivitySummary())->series([
            ['date' => '2026-11-25', 'color' => 'green', 'count' => 3],
            ['date' => '2026-11-25', 'color' => 'red', 'count' => 7],
            ['date' => '2026-11-24', 'color' => 'green', 'count' => 5],
            ['date' => '2026-11-24', 'color' => 'red', 'count' => 5],
            ['date' => '2026-11-26', 'color' => 'green', 'count' => 3],
            ['date' => '2026-11-26', 'color' => 'red', 'count' => 7],
        ]);

        $this->assertSame(['2026-11-24', '2026-11-25', '2026-11-26'], array_column($series, 'date'));
        $this->assertSame(50.0, $series[0]['percent']);
        $this->assertSame('down', $series[1]['direction']);
        $this->assertSame(-20.0, $series[1]['delta']);
        $this->assertSame('flat', $series[2]['direction']);
        $this->assertSame(0.0, $series[2]['delta']);
    }

    public function test_unknown_color_and_empty_day_are_skipped(): void
    {
        $series = (new ClientActivitySummary())->series([
            ['date' => '', 'color' => 'green', 'count' => 5],
            ['date' => '2026-11-24', 'color' => 'blue', 'count' => 9],
        ]);

        $this->assertSame([], $series);
    }
}
