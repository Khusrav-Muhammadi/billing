<?php

namespace App\Services\ClientHealth;

use App\Models\ClientHealthSnapshot;
use Illuminate\Support\Carbon;

/**
 * Индекс здоровья клиента (ТЗ «светофор», Nov 23, 2026). Чистый расчёт без БД и HTTP.
 *
 * Баллы за 7 дней (неделя 0 из метрик CRM):
 *  - Входы (20%): сколько дней в среднем заходил каждый пользователь; 4 дня и больше = 100.
 *    Среднее по всем пользователям компании: кто не заходил, тот даёт 0.
 *  - Охват (30%): доля пользователей, которые заходили хотя бы раз.
 *  - Диалоги (25%): обработанные диалоги против среднего за 4 прошлые недели.
 *  - Сделки (25%): новые лиды + новые сделки + смены этапа против среднего за 4 прошлые недели.
 *    Сравнение: неделя / среднее × 100, но не больше 100. Если за 5 недель ноль — пункт не
 *    считается, его вес делится на остальные. Если база 0, а сейчас есть — 100.
 *
 * Цвет:
 *  - зелёный — 70 и выше;
 *  - жёлтый — 40–69, или индекс упал на 20+ за 2 недели;
 *  - красный — ниже 40, или 7 дней никто не заходил, или заходит меньше 30% пользователей,
 *    или до конца оплаты 30 дней и меньше при жёлтом цвете (только платные);
 *  - серый — первые 28 дней после оплаты (платные). Серый перекрывает всё, кроме
 *    «7 дней никто не заходил»: это остаётся красным даже у нового клиента.
 */
class ClientHealthCalculator
{
    public const WEIGHTS = [
        'logins' => 20,
        'coverage' => 30,
        'dialogs' => 25,
        'deals' => 25,
    ];

    public const LOGIN_DAYS_FOR_MAX = 4;
    public const GREEN_FROM = 70;
    public const YELLOW_FROM = 40;
    public const MIN_COVERAGE = 30;
    public const DROP_POINTS = 20;
    public const DROP_DAYS = 14;
    public const PAID_SOON_DAYS = 30;
    public const NEW_CLIENT_DAYS = 28;

    private const LABELS = [
        'logins' => 'входы',
        'coverage' => 'охват',
        'dialogs' => 'диалоги',
        'deals' => 'сделки',
    ];

    /**
     * @param array $metrics ответ CRM: users_total, activity{active_users, active_user_days, tracked_since, backfilled_since}, weeks[0..4]
     * @param array $context date (Y-m-d), client_type (paid|demo), paid_until (?Y-m-d), period_started_at (?Y-m-d), previous_index (?int, индекс 14 дней назад)
     */
    public function calculate(array $metrics, array $context): array
    {
        $date = Carbon::parse($context['date'])->startOfDay();
        $isPaid = ($context['client_type'] ?? 'paid') === 'paid';

        $usersTotal = max(0, (int) ($metrics['users_total'] ?? 0));
        $activeUsers = max(0, (int) data_get($metrics, 'activity.active_users', 0));
        $activeUserDays = max(0, (int) data_get($metrics, 'activity.active_user_days', 0));

        $weeks = $this->weeks($metrics['weeks'] ?? []);

        $scores = [
            'logins' => $usersTotal > 0
                ? $this->cap(($activeUserDays / $usersTotal) / self::LOGIN_DAYS_FOR_MAX * 100)
                : 0.0,
            'coverage' => $usersTotal > 0 ? $this->cap($activeUsers / $usersTotal * 100) : 0.0,
            'dialogs' => $this->compare(array_column($weeks, 'dialogs')),
            'deals' => $this->compare(array_map(
                fn (array $w) => $w['leads_new'] + $w['deals_new'] + $w['stage_changes'],
                $weeks
            )),
        ];

        $index = $this->index($scores);
        $coverage = $scores['coverage'];

        $previous = isset($context['previous_index']) && $context['previous_index'] !== null
            ? (int) $context['previous_index']
            : null;
        $drop = $previous !== null ? $previous - $index : null;

        $paidUntil = !empty($context['paid_until']) ? Carbon::parse($context['paid_until'])->startOfDay() : null;
        $daysLeft = $paidUntil ? (int) $date->diffInDays($paidUntil, false) : null;

        $periodStart = !empty($context['period_started_at']) ? Carbon::parse($context['period_started_at'])->startOfDay() : null;
        $dayOfPeriod = $periodStart ? (int) $periodStart->diffInDays($date, false) + 1 : null;
        $isNew = $isPaid && $dayOfPeriod !== null && $dayOfPeriod >= 1 && $dayOfPeriod <= self::NEW_CLIENT_DAYS;

        $nobody = $activeUsers === 0;
        $lowCoverage = !$nobody && $coverage < self::MIN_COVERAGE;

        // Цвет по индексу и падению.
        $color = match (true) {
            $index >= self::GREEN_FROM => ClientHealthSnapshot::GREEN,
            $index >= self::YELLOW_FROM => ClientHealthSnapshot::YELLOW,
            default => ClientHealthSnapshot::RED,
        };
        $dropped = $drop !== null && $drop >= self::DROP_POINTS;
        if ($dropped && $color === ClientHealthSnapshot::GREEN) {
            $color = ClientHealthSnapshot::YELLOW;
        }
        $paidSoon = $isPaid && $daysLeft !== null && $daysLeft <= self::PAID_SOON_DAYS;
        $paidSoonRed = $paidSoon && $color === ClientHealthSnapshot::YELLOW;

        if ($nobody || $lowCoverage || $paidSoonRed) {
            $color = ClientHealthSnapshot::RED;
        }

        if ($isNew && !$nobody) {
            $color = ClientHealthSnapshot::GREY;
        }

        // Причины: сначала то, что решило цвет, потом пояснения.
        $reasons = [];
        $isGrey = $color === ClientHealthSnapshot::GREY;
        if ($isGrey) {
            $reasons[] = $this->reason('new', "Новый клиент: {$dayOfPeriod}-й день из " . self::NEW_CLIENT_DAYS);
        }
        if ($nobody) {
            $reasons[] = $this->reason('no_logins', '7 дней никто не заходил');
        }
        if ($lowCoverage) {
            $reasons[] = $this->reason('low_coverage', sprintf(
                'Заходят %d%% пользователей (%d из %d), нужно от %d%%',
                (int) floor($coverage), $activeUsers, $usersTotal, self::MIN_COVERAGE
            ));
        }
        if ($index < self::YELLOW_FROM) {
            $reasons[] = $this->reason('low_index', "Индекс {$index} ниже " . self::YELLOW_FROM);
        }
        if ($paidSoonRed) {
            $reasons[] = $this->reason('paid_soon', $daysLeft >= 0
                ? "Жёлтый (индекс {$index}) и до конца оплаты {$daysLeft} дн."
                : "Жёлтый (индекс {$index}) и оплата закончилась");
        }
        if ($dropped) {
            $reasons[] = $this->reason('drop', "Индекс упал на {$drop} за 2 недели (было {$previous})");
        }
        if ($color === ClientHealthSnapshot::YELLOW && $index < self::GREEN_FROM) {
            $reasons[] = $this->reason('mid_index', "Индекс {$index}: от " . self::YELLOW_FROM . ' до ' . (self::GREEN_FROM - 1));
        }
        if ($isNew && !$isGrey) {
            $reasons[] = $this->reason('new_info', "Новый клиент: {$dayOfPeriod}-й день из " . self::NEW_CLIENT_DAYS);
        }

        $weak = [];
        foreach ($scores as $key => $score) {
            if ($score !== null && $score < 50) {
                $weak[] = self::LABELS[$key] . ' ' . (int) round($score);
            }
        }
        if ($weak !== [] && $color !== ClientHealthSnapshot::GREEN) {
            $reasons[] = $this->reason('weak', 'Слабо: ' . implode(', ', $weak));
        }

        foreach (['dialogs' => 'Диалоги', 'deals' => 'Сделки'] as $key => $label) {
            if ($scores[$key] === null) {
                $reasons[] = $this->reason('skipped_' . $key, "{$label} не считаются: за 5 недель их не было");
            }
        }

        if ($note = $this->trackingNote($metrics, $date)) {
            $reasons[] = $this->reason('tracking', $note);
        }

        $decisive = array_filter($reasons, fn ($r) => !in_array($r['code'], ['tracking', 'skipped_dialogs', 'skipped_deals'], true));
        $reasonText = $color === ClientHealthSnapshot::GREEN && $decisive === []
            ? 'Всё в норме'
            : implode('; ', array_column($decisive ?: $reasons, 'text'));

        return [
            'logins_score' => $scores['logins'],
            'coverage_score' => $scores['coverage'],
            'dialogs_score' => $scores['dialogs'],
            'deals_score' => $scores['deals'],
            'health_index' => $index,
            'color' => $color,
            'reasons' => array_values($reasons),
            'reason_text' => mb_substr($reasonText, 0, 500),
        ];
    }

    /** Индекс 0–100: баллы × веса, вес выпавших пунктов делится между остальными. */
    public function index(array $scores): int
    {
        $sum = 0.0;
        $weights = 0;
        foreach (self::WEIGHTS as $key => $weight) {
            if (!array_key_exists($key, $scores) || $scores[$key] === null) {
                continue;
            }
            $sum += $scores[$key] * $weight;
            $weights += $weight;
        }

        return $weights > 0 ? (int) round($sum / $weights) : 0;
    }

    /**
     * @param array<int, int|float> $byWeek [неделя 0, неделя 1, ..., неделя 4]
     */
    public function compare(array $byWeek): ?float
    {
        $current = (float) ($byWeek[0] ?? 0);
        $previous = array_slice(array_values($byWeek), 1, 4);
        $baseline = array_sum($previous) / 4;

        if ($baseline <= 0 && $current <= 0) {
            return null;
        }
        if ($baseline <= 0) {
            return 100.0;
        }

        return $this->cap($current / $baseline * 100);
    }

    private function weeks(array $weeks): array
    {
        $result = [];
        for ($w = 0; $w < 5; $w++) {
            $result[$w] = ['dialogs' => 0, 'leads_new' => 0, 'deals_new' => 0, 'stage_changes' => 0];
        }
        foreach ($weeks as $i => $week) {
            $w = (int) ($week['week'] ?? $i);
            if ($w < 0 || $w > 4) {
                continue;
            }
            foreach (array_keys($result[$w]) as $key) {
                $result[$w][$key] = max(0, (int) ($week[$key] ?? 0));
            }
        }

        return $result;
    }

    /** Окно входов захватывает дни, за которые учёта ещё не было. */
    private function trackingNote(array $metrics, Carbon $date): ?string
    {
        $weekFrom = $date->copy()->subDays(6)->toDateString();
        $tracked = data_get($metrics, 'activity.tracked_since');
        $backfilled = data_get($metrics, 'activity.backfilled_since');
        $since = collect([$tracked, $backfilled])->filter()->min();

        if ($since === null) {
            return 'Учёт входов в CRM ещё не включён: входы и охват 0';
        }
        if ($since > $weekFrom) {
            return "Учёт входов неполный: данные с {$since}";
        }

        return null;
    }

    private function cap(float $value): float
    {
        return round(max(0.0, min(100.0, $value)), 1);
    }

    private function reason(string $code, string $text): array
    {
        return ['code' => $code, 'text' => $text];
    }
}
