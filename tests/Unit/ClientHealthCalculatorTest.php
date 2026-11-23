<?php

namespace Tests\Unit;

use App\Services\ClientHealth\ClientHealthCalculator;
use PHPUnit\Framework\TestCase;

class ClientHealthCalculatorTest extends TestCase
{
    private function metrics(array $override = []): array
    {
        $week = fn (int $w, int $dialogs, int $deals) => [
            'week' => $w, 'dialogs' => $dialogs, 'leads_new' => $deals, 'deals_new' => 0, 'stage_changes' => 0,
        ];

        return array_replace_recursive([
            'users_total' => 10,
            'activity' => [
                'active_users' => 10,
                'active_user_days' => 40,
                'tracked_since' => '2026-01-01',
                'backfilled_since' => null,
            ],
            'weeks' => [$week(0, 20, 10), $week(1, 20, 10), $week(2, 20, 10), $week(3, 20, 10), $week(4, 20, 10)],
        ], $override);
    }

    private function context(array $override = []): array
    {
        return $override + [
            'date' => '2026-11-22',
            'client_type' => 'paid',
            'paid_until' => '2027-03-01',
            'period_started_at' => '2025-01-01',
            'previous_index' => null,
        ];
    }

    public function test_healthy_client_is_green_with_full_index(): void
    {
        $r = (new ClientHealthCalculator())->calculate($this->metrics(), $this->context());

        $this->assertSame(100, $r['health_index']);
        $this->assertSame('green', $r['color']);
        $this->assertSame('Всё в норме', $r['reason_text']);
    }

    public function test_four_login_days_cap_at_100_and_are_averaged_over_all_users(): void
    {
        // 10 пользователей, 20 пользователе-дней: в среднем 2 дня -> 50 баллов.
        $r = (new ClientHealthCalculator())->calculate($this->metrics(['activity' => ['active_user_days' => 20]]), $this->context());

        $this->assertSame(50.0, $r['logins_score']);
        // 50*20 + 100*30 + 100*25 + 100*25 = 9000 / 100
        $this->assertSame(90, $r['health_index']);
    }

    public function test_missing_dialogs_weight_is_shared_by_the_rest(): void
    {
        $m = $this->metrics();
        foreach ($m['weeks'] as &$w) {
            $w['dialogs'] = 0;
        }
        unset($w);
        $m['activity']['active_user_days'] = 20; // входы 50

        $r = (new ClientHealthCalculator())->calculate($m, $this->context());

        $this->assertNull($r['dialogs_score']);
        // (50*20 + 100*30 + 100*25) / 75 = 86.67
        $this->assertSame(87, $r['health_index']);
    }

    public function test_compare_with_previous_four_weeks(): void
    {
        $calc = new ClientHealthCalculator();

        $this->assertSame(50.0, $calc->compare([10, 20, 20, 20, 20]));
        $this->assertSame(100.0, $calc->compare([40, 20, 20, 20, 20]));
        $this->assertSame(100.0, $calc->compare([5, 0, 0, 0, 0]));
        $this->assertNull($calc->compare([0, 0, 0, 0, 0]));
    }

    public function test_nobody_logged_in_is_red_even_for_new_client(): void
    {
        $r = (new ClientHealthCalculator())->calculate(
            $this->metrics(['activity' => ['active_users' => 0, 'active_user_days' => 0]]),
            $this->context(['period_started_at' => '2026-11-15'])
        );

        $this->assertSame('red', $r['color']);
        $this->assertStringContainsString('7 дней никто не заходил', $r['reason_text']);
    }

    public function test_new_client_is_grey_for_28_days(): void
    {
        $calc = new ClientHealthCalculator();
        $low = $this->metrics(['activity' => ['active_users' => 2, 'active_user_days' => 2]]);

        $this->assertSame('grey', $calc->calculate($low, $this->context(['period_started_at' => '2026-10-26']))['color']); // 28-й день
        $this->assertSame('red', $calc->calculate($low, $this->context(['period_started_at' => '2026-10-25']))['color']);  // 29-й день
    }

    public function test_low_coverage_is_red(): void
    {
        $r = (new ClientHealthCalculator())->calculate(
            $this->metrics(['activity' => ['active_users' => 2, 'active_user_days' => 8]]),
            $this->context()
        );

        $this->assertSame('red', $r['color']);
        $this->assertStringContainsString('Заходят 20% пользователей', $r['reason_text']);
    }

    public function test_drop_of_20_points_makes_green_yellow(): void
    {
        $r = (new ClientHealthCalculator())->calculate($this->metrics(), $this->context(['previous_index' => 100]));
        $this->assertSame('green', $r['color']);

        // Падение на 14 — ещё зелёный.
        $m = $this->metrics(['activity' => ['active_user_days' => 12]]); // входы 30 -> (600+3000+5000)/100 = 86
        $r = (new ClientHealthCalculator())->calculate($m, $this->context(['previous_index' => 100]));
        $this->assertSame(86, $r['health_index']);
        $this->assertSame('green', $r['color']);

        // Падение на 22 при индексе 78 — жёлтый.
        $m = $this->metrics(['activity' => ['active_users' => 8, 'active_user_days' => 8]]); // входы 20, охват 80 -> (400+2400+5000)/100 = 78
        $r = (new ClientHealthCalculator())->calculate($m, $this->context(['previous_index' => 100]));
        $this->assertSame(78, $r['health_index']);
        $this->assertSame('yellow', $r['color']);
        $this->assertStringContainsString('упал на 22', $r['reason_text']);
    }

    public function test_yellow_with_payment_ending_in_30_days_is_red(): void
    {
        $m = $this->metrics(['activity' => ['active_users' => 4, 'active_user_days' => 4]]); // охват 40, входы 10 -> 2+12+25+25 = 64
        $calc = new ClientHealthCalculator();

        $this->assertSame('yellow', $calc->calculate($m, $this->context(['paid_until' => '2026-12-23']))['color']); // 31 день
        $this->assertSame('red', $calc->calculate($m, $this->context(['paid_until' => '2026-12-22']))['color']);    // 30 дней
        // У демо срок оплаты не учитывается.
        $this->assertSame('yellow', $calc->calculate($m, $this->context(['client_type' => 'demo', 'paid_until' => '2026-11-25', 'period_started_at' => null]))['color']);
    }
}
