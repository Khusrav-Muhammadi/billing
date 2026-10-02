<?php

namespace App\Services\Ai;

use App\Models\Ai\AiUsageLog;
use Illuminate\Support\Carbon;

/**
 * Считает, сколько организация потратила на использование ИИ.
 * Берём сумму из ai_usage_logs.total_cost — это уже списанная стоимость токенов.
 * Оплату тарифа сюда не включаем: она уже видна в карточке подписки.
 */
class AiUsageSpendSummary
{
    private const TIMEZONE = 'Asia/Dushanbe';

    /** Русские названия месяцев для подписи «в этом месяце». */
    private const RU_MONTHS = [
        1 => 'январь',
        2 => 'февраль',
        3 => 'март',
        4 => 'апрель',
        5 => 'май',
        6 => 'июнь',
        7 => 'июль',
        8 => 'август',
        9 => 'сентябрь',
        10 => 'октябрь',
        11 => 'ноябрь',
        12 => 'декабрь',
    ];

    /**
     * @return array{all_time: float, this_month: float, month_label: string}
     */
    public function forOrganization(int $organizationId, ?Carbon $now = null): array
    {
        $now = ($now ?? Carbon::now(self::TIMEZONE))->copy()->timezone(self::TIMEZONE);
        $range = $this->currentMonthRange($now);

        // Один запрос: итог за всё время и отдельно за календарный месяц.
        $row = AiUsageLog::query()
            ->where('organization_id', $organizationId)
            ->selectRaw('COALESCE(SUM(total_cost), 0) as all_time')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN period_start >= ? AND period_start <= ? THEN total_cost ELSE 0 END), 0) as this_month',
                [$range['start'], $range['end']]
            )
            ->first();

        return [
            'all_time' => round((float) ($row->all_time ?? 0), 6),
            'this_month' => round((float) ($row->this_month ?? 0), 6),
            'month_label' => $this->monthLabel($now),
        ];
    }

    /**
     * Календарный месяц в часовом поясе биллинга.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function currentMonthRange(Carbon $now): array
    {
        $local = $now->copy()->timezone(self::TIMEZONE);

        return [
            'start' => $local->copy()->startOfMonth(),
            'end' => $local->copy()->endOfMonth(),
        ];
    }

    public function monthLabel(Carbon $now): string
    {
        $local = $now->copy()->timezone(self::TIMEZONE);
        $name = self::RU_MONTHS[(int) $local->month] ?? $local->format('m');

        return $name . ' ' . $local->format('Y');
    }
}
