<?php

namespace Tests\Unit;

use App\Services\ClientHealth\ClientHealthCalculator;
use PHPUnit\Framework\TestCase;

class ClientHealthCalculatorTest extends TestCase
{
    private function metrics(array $override = []): array
    {
        $week = fn (int $w, int $dialogs, int $entities) => [
            'week' => $w, 'dialogs' => $dialogs, 'leads_new' => $entities, 'deals_new' => 0,
            'orders_new' => 0, 'goods_new' => 0, 'stage_changes' => 0,
        ];

        return array_replace_recursive([
            'users_total' => 10,
            'activity' => [
                'active_users' => 10,
                'active_user_days' => 40,
                'active_days' => 7,
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

    public function test_logins_count_days_when_anyone_worked_five_days_is_max(): void
    {
        $calc = new ClientHealthCalculator();

        // 3 дня из 7 работал хоть кто-то -> 60 баллов.
        $r = $calc->calculate($this->metrics(['activity' => ['active_days' => 3]]), $this->context());
        $this->assertSame(60.0, $r['logins_score']);
        // 60*15 + 100*10 + 100*35 + 100*40 = 9400 / 100
        $this->assertSame(94, $r['health_index']);

        $this->assertSame(100.0, $calc->calculate($this->metrics(['activity' => ['active_days' => 5]]), $this->context())['logins_score']);
    }

    public function test_old_crm_without_active_days_falls_back_to_user_days(): void
    {
        $m = $this->metrics(['activity' => ['active_user_days' => 2]]);
        unset($m['activity']['active_days']);

        $this->assertSame(40.0, (new ClientHealthCalculator())->calculate($m, $this->context())['logins_score']);
    }

    public function test_three_of_ten_users_is_full_coverage_and_green(): void
    {
        // Как у Эсхаты/Кедра: работают 2–3 человека каждый день, много диалогов и лидов.
        $r = (new ClientHealthCalculator())->calculate(
            $this->metrics(['activity' => ['active_users' => 3, 'active_user_days' => 15, 'active_days' => 7]]),
            $this->context()
        );

        $this->assertSame(100.0, $r['coverage_score']);
        $this->assertSame(100, $r['health_index']);
        $this->assertSame('green', $r['color']);
    }

    public function test_low_coverage_lowers_index_but_is_not_red_by_itself(): void
    {
        // 2 из 20 = 10% -> охват 33.3 балла.
        $r = (new ClientHealthCalculator())->calculate(
            $this->metrics(['users_total' => 20, 'activity' => ['active_users' => 2, 'active_user_days' => 10, 'active_days' => 7]]),
            $this->context()
        );

        $this->assertSame(33.3, $r['coverage_score']);
        // 100*15 + 33.3*10 + 100*35 + 100*40 = 9833 / 100
        $this->assertSame(93, $r['health_index']);
        $this->assertSame('green', $r['color']);
    }

    public function test_missing_dialogs_weight_is_shared_by_the_rest(): void
    {
        $m = $this->metrics();
        foreach ($m['weeks'] as &$w) {
            $w['dialogs'] = 0;
        }
        unset($w);
        $m['activity']['active_days'] = 2; // входы 40

        $r = (new ClientHealthCalculator())->calculate($m, $this->context());

        $this->assertNull($r['dialogs_score']);
        // (40*15 + 100*10 + 100*40) / 65 = 86.15
        $this->assertSame(86, $r['health_index']);
    }

    public function test_entities_count_leads_deals_orders_goods_and_stage_changes(): void
    {
        $m = $this->metrics();
        // Неделя 0: 2 лида + 2 сделки + 3 заказа + 1 товар + 2 смены этапа = 10, база по 20 -> 50 баллов.
        $m['weeks'][0] = ['week' => 0, 'dialogs' => 20, 'leads_new' => 2, 'deals_new' => 2, 'orders_new' => 3, 'goods_new' => 1, 'stage_changes' => 2];
        foreach ([1, 2, 3, 4] as $w) {
            $m['weeks'][$w] = ['week' => $w, 'dialogs' => 20, 'leads_new' => 0, 'deals_new' => 0, 'orders_new' => 15, 'goods_new' => 5, 'stage_changes' => 0];
        }

        $r = (new ClientHealthCalculator())->calculate($m, $this->context());

        $this->assertSame(50.0, $r['entities_score']);
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
        // Заходят, но работы почти нет: диалоги и сущности 10% от прошлых недель -> индекс ниже 40.
        $low = $this->metrics(['activity' => ['active_users' => 1, 'active_user_days' => 1, 'active_days' => 1]]);
        $low['weeks'][0]['dialogs'] = 2;
        $low['weeks'][0]['leads_new'] = 1;

        $this->assertSame('grey', $calc->calculate($low, $this->context(['period_started_at' => '2026-10-26']))['color']); // 28-й день
        $this->assertSame('red', $calc->calculate($low, $this->context(['period_started_at' => '2026-10-25']))['color']);  // 29-й день
    }

    public function test_drop_of_20_points_makes_green_yellow(): void
    {
        $calc = new ClientHealthCalculator();
        $r = $calc->calculate($this->metrics(), $this->context(['previous_index' => 100]));
        $this->assertSame('green', $r['color']);

        // Диалоги упали вдвое: 100*15 + 100*10 + 50*35 + 100*40 = 8250 -> 83, падение на 17 — ещё зелёный.
        $m = $this->metrics();
        $m['weeks'][0]['dialogs'] = 10;
        $r = $calc->calculate($m, $this->context(['previous_index' => 100]));
        $this->assertSame(83, $r['health_index']);
        $this->assertSame('green', $r['color']);

        // Диалоги и сущности вдвое: 1500 + 1000 + 1750 + 2000 = 6250 -> 63 при прошлом 100 — жёлтый.
        $m['weeks'][0]['leads_new'] = 5;
        $r = $calc->calculate($m, $this->context(['previous_index' => 85]));
        $this->assertSame(63, $r['health_index']);
        $this->assertSame('yellow', $r['color']);
        $this->assertStringContainsString('упал на 22', $r['reason_text']);
    }

    public function test_yellow_with_payment_ending_in_30_days_is_red(): void
    {
        $m = $this->metrics();
        $m['weeks'][0]['dialogs'] = 10;
        $m['weeks'][0]['leads_new'] = 5; // индекс 63 -> жёлтый
        $calc = new ClientHealthCalculator();

        $this->assertSame('yellow', $calc->calculate($m, $this->context(['paid_until' => '2026-12-23']))['color']); // 31 день
        $this->assertSame('red', $calc->calculate($m, $this->context(['paid_until' => '2026-12-22']))['color']);    // 30 дней
        // У демо срок оплаты не учитывается.
        $this->assertSame('yellow', $calc->calculate($m, $this->context(['client_type' => 'demo', 'paid_until' => '2026-11-25', 'period_started_at' => null]))['color']);
    }
}
