<?php

namespace App\Services\ClientHealth;

use App\Models\ClientHealthSnapshot;
use Illuminate\Support\Carbon;

/**
 * Индекс здоровья клиента (ТЗ «светофор», Nov 23, 2026). Чистый расчёт без БД и HTTP.
 *
 * Главное — работа в CRM (75%), а не то, сколько сотрудников заходит: нормально, если
 * из 10 сотрудников в CRM работают 2–3 человека (правка от 24.11.2026).
 *
 * Баллы за 7 дней (неделя 0 из метрик CRM):
 *  - Входы (15%): в сколько дней из 7 в CRM работал хоть кто-то из компании; 5 дней и больше = 100.
 *  - Охват (10%): доля пользователей, которые заходили хотя бы раз; 30% и больше = 100.
 *  - Диалоги (35%): обработанные диалоги против обычной недели (медиана 4 прошлых недель).
 *  - Сущности (40%): новые лиды, сделки, заказы и товары + смены этапа (лиды, сделки, заказы)
 *    против обычной недели.
 *    Сравнение: неделя / медиана × 100, но не больше 100. Медиана, а не среднее: одна неделя
 *    с импортом или массовым переносом этапов не завышает базу.
 *    Пункт не считается (вес делится на остальные), если клиент им почти не пользуется:
 *    медиана 0 и сейчас 0 (было не больше чем в половине недель), или и в обычную, и в эту
 *    неделю это меньше 10% всей работы (диалоги + сущности). Нужно для клиентов, которые
 *    работают с лидами без чатов, и наоборот. Если база 0, а сейчас есть — 100.
 *
 * Цвет:
 *  - зелёный — 70 и выше;
 *  - жёлтый — 40–69, или индекс упал на 20+ за 2 недели;
 *  - красный — ниже 40, или 7 дней никто не заходил (тогда и диалоги с сущностями = 0:
 *    без входов это поток из интеграций и ответы с телефона, а не работа в CRM),
 *    или до конца оплаты 30 дней и меньше при жёлтом цвете (только платные);
 *  - серый — первые 28 дней после оплаты (платные). Серый перекрывает всё, кроме
 *    «7 дней никто не заходил»: это остаётся красным даже у нового клиента.
 */
class ClientHealthCalculator
{
    public const WEIGHTS = [
        'logins' => 15,
        'coverage' => 10,
        'dialogs' => 35,
        'entities' => 40,
    ];

    /** Дней из 7, в которые кто-то работал в CRM, для 100 баллов «Входов» (рабочая неделя). */
    public const LOGIN_DAYS_FOR_MAX = 5;
    /** Доля пользователей (%), с которой «Охват» = 100. */
    public const COVERAGE_FOR_MAX = 30;
    public const GREEN_FROM = 70;
    public const YELLOW_FROM = 40;
    public const DROP_POINTS = 20;
    public const DROP_DAYS = 14;
    public const PAID_SOON_DAYS = 30;
    public const NEW_CLIENT_DAYS = 28;
    /** Доля пункта в работе клиента (диалоги + сущности), ниже которой пункт не считается. */
    public const MINOR_SHARE = 0.10;

    private const LABELS = [
        'logins' => 'входы',
        'coverage' => 'охват',
        'dialogs' => 'диалоги',
        'entities' => 'сущности',
    ];

    /**
     * @param array $metrics ответ CRM: users_total, activity{active_users, active_user_days, active_days, tracked_since, backfilled_since}, weeks[0..4]
     * @param array $context date (Y-m-d), client_type (paid|demo), paid_until (?Y-m-d), period_started_at (?Y-m-d), previous_index (?int, индекс 14 дней назад), last_activity (?дата последнего входа из clients)
     */
    public function calculate(array $metrics, array $context): array
    {
        $date = Carbon::parse($context['date'])->startOfDay();
        $isPaid = ($context['client_type'] ?? 'paid') === 'paid';

        $usersTotal = max(0, (int) ($metrics['users_total'] ?? 0));
        $activeUsers = max(0, (int) data_get($metrics, 'activity.active_users', 0));
        $activeUserDays = max(0, (int) data_get($metrics, 'activity.active_user_days', 0));
        // Дни, когда в CRM работал хоть кто-то. Старая CRM этого не отдаёт: тогда берём
        // пользователе-дни (не больше 7) — это верхняя оценка, до выкладки CRM.
        $activeDays = data_get($metrics, 'activity.active_days');
        $activeDays = $activeDays !== null ? max(0, min(7, (int) $activeDays)) : min(7, $activeUserDays);

        $weeks = $this->weeks($metrics['weeks'] ?? []);

        $work = [
            'dialogs' => array_column($weeks, 'dialogs'),
            'entities' => array_map(
                fn (array $w) => $w['leads_new'] + $w['deals_new'] + $w['orders_new'] + $w['goods_new'] + $w['stage_changes'],
                $weeks
            ),
        ];
        $minor = $this->minorWork($work);

        $scores = [
            'logins' => $this->cap($activeDays / self::LOGIN_DAYS_FOR_MAX * 100),
            'coverage' => $usersTotal > 0 ? $this->cap($activeUsers / $usersTotal * 100 / self::COVERAGE_FOR_MAX * 100) : 0.0,
            'dialogs' => in_array('dialogs', $minor, true) ? null : $this->compare($work['dialogs']),
            'entities' => in_array('entities', $minor, true) ? null : $this->compare($work['entities']),
        ];

        // 7 дней никто не заходил: лиды и диалоги, которые всё равно есть, — не работа в CRM.
        // Лиды создают интеграции, а «диалоги» — ответы с телефона (WhatsApp и т.п.), которые
        // CRM только зеркалит. Иначе клиент, который не открывал CRM с сентября, получает 75.
        $nobody = $activeUsers === 0;
        $autoOnly = $nobody && (($work['dialogs'][0] ?? 0) + ($work['entities'][0] ?? 0)) > 0;
        if ($nobody) {
            foreach (['dialogs', 'entities'] as $key) {
                if ($scores[$key] !== null) {
                    $scores[$key] = 0.0;
                }
            }
        }

        $index = $this->index($scores);

        $previous = isset($context['previous_index']) && $context['previous_index'] !== null
            ? (int) $context['previous_index']
            : null;
        $drop = $previous !== null ? $previous - $index : null;

        $paidUntil = !empty($context['paid_until']) ? Carbon::parse($context['paid_until'])->startOfDay() : null;
        $daysLeft = $paidUntil ? (int) $date->diffInDays($paidUntil, false) : null;

        $periodStart = !empty($context['period_started_at']) ? Carbon::parse($context['period_started_at'])->startOfDay() : null;
        $dayOfPeriod = $periodStart ? (int) $periodStart->diffInDays($date, false) + 1 : null;
        $isNew = $isPaid && $dayOfPeriod !== null && $dayOfPeriod >= 1 && $dayOfPeriod <= self::NEW_CLIENT_DAYS;

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

        if ($nobody || $paidSoonRed) {
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
            $last = !empty($context['last_activity']) ? Carbon::parse($context['last_activity']) : null;
            $reasons[] = $this->reason('no_logins', $last && $last->lt($date->copy()->subDays(6))
                ? '7 дней никто не заходил (последний вход ' . $last->format('d.m.Y') . ')'
                : '7 дней никто не заходил');
        }
        if ($autoOnly) {
            $reasons[] = $this->reason('auto_only', 'Лиды и диалоги приходят сами (интеграции, ответы с телефона), но в CRM никто не работает: не считаются');
        }
        if ($index < self::YELLOW_FROM && !$nobody) {
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
        if ($weak !== [] && $color !== ClientHealthSnapshot::GREEN && !$nobody) {
            $reasons[] = $this->reason('weak', 'Слабо: ' . implode(', ', $weak));
        }

        foreach (['dialogs' => 'Диалоги', 'entities' => 'Сущности'] as $key => $label) {
            if ($scores[$key] === null) {
                $reasons[] = $this->reason('skipped_' . $key, in_array($key, $minor, true)
                    ? "{$label} не считаются: это меньше 10% работы клиента"
                    : "{$label} не считаются: клиент ими почти не пользуется");
            }
        }

        if ($note = $this->trackingNote($metrics, $date)) {
            $reasons[] = $this->reason('tracking', $note);
        }

        $decisive = array_filter($reasons, fn ($r) => !in_array($r['code'], ['tracking', 'skipped_dialogs', 'skipped_entities'], true));
        $reasonText = $color === ClientHealthSnapshot::GREEN && $decisive === []
            ? 'Всё в норме'
            : implode('; ', array_column($decisive ?: $reasons, 'text'));

        return [
            'logins_score' => $scores['logins'],
            'coverage_score' => $scores['coverage'],
            'dialogs_score' => $scores['dialogs'],
            'entities_score' => $scores['entities'],
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
        $baseline = $this->baseline($byWeek);

        if ($baseline <= 0 && $current <= 0) {
            return null;
        }
        if ($baseline <= 0) {
            return 100.0;
        }

        return $this->cap($current / $baseline * 100);
    }

    /** Обычная неделя: медиана 4 прошлых недель. */
    public function baseline(array $byWeek): float
    {
        $previous = array_map('floatval', array_pad(array_slice(array_values($byWeek), 1, 4), 4, 0));
        sort($previous);

        return ($previous[1] + $previous[2]) / 2;
    }

    /**
     * Пункты работы, которыми клиент почти не пользуется: и в обычную неделю, и в эту
     * они меньше MINOR_SHARE от диалогов + сущностей.
     *
     * @param array{dialogs: array, entities: array} $work
     * @return string[]
     */
    private function minorWork(array $work): array
    {
        $base = array_map(fn (array $w) => $this->baseline($w), $work);
        $now = array_map(fn (array $w) => (float) ($w[0] ?? 0), $work);
        $baseTotal = array_sum($base);
        $nowTotal = array_sum($now);

        $minor = [];
        foreach ($work as $key => $_) {
            $baseShare = $baseTotal > 0 ? $base[$key] / $baseTotal : 0;
            $nowShare = $nowTotal > 0 ? $now[$key] / $nowTotal : 0;
            if ($baseTotal > 0 && $baseShare < self::MINOR_SHARE && $nowShare < self::MINOR_SHARE) {
                $minor[] = $key;
            }
        }

        return $minor;
    }

    private function weeks(array $weeks): array
    {
        $result = [];
        for ($w = 0; $w < 5; $w++) {
            $result[$w] = ['dialogs' => 0, 'leads_new' => 0, 'deals_new' => 0, 'orders_new' => 0, 'goods_new' => 0, 'stage_changes' => 0];
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
            return 'С начала учёта входов в CRM никто не заходил';
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
