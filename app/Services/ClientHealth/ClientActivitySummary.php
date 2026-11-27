<?php

namespace App\Services\ClientHealth;

use App\Models\ClientHealthSnapshot;

/**
 * Общая активность по уже посчитанным дням светофора.
 * Работают — зелёный и жёлтый. Серые (новые) и красные в долю не входят.
 */
class ClientActivitySummary
{
    /**
     * @param iterable<array{date: string, color: string, count: int}> $rows
     * @return list<array{date: string, total: int, working: int, percent: float, delta: ?float, direction: ?string, colors: array{red: int, yellow: int, grey: int, green: int}}>
     */
    public function series(iterable $rows): array
    {
        $byDate = [];
        foreach ($rows as $row) {
            $date = (string) ($row['date'] ?? '');
            $color = (string) ($row['color'] ?? '');
            if ($date === '' || !in_array($color, ClientHealthSnapshot::COLOR_ORDER, true)) {
                continue;
            }
            $byDate[$date][$color] = ($byDate[$date][$color] ?? 0) + (int) $row['count'];
        }

        ksort($byDate);

        $series = [];
        $previous = null;
        foreach ($byDate as $date => $counts) {
            $colors = array_fill_keys(ClientHealthSnapshot::COLOR_ORDER, 0);
            foreach ($counts as $color => $count) {
                $colors[$color] = $count;
            }

            $total = array_sum($colors);
            $working = $colors[ClientHealthSnapshot::GREEN] + $colors[ClientHealthSnapshot::YELLOW];
            $percent = $total > 0 ? round($working / $total * 100, 1) : 0.0;
            $delta = $previous === null ? null : round($percent - $previous, 1);

            $series[] = [
                'date' => $date,
                'total' => $total,
                'working' => $working,
                'percent' => $percent,
                'delta' => $delta,
                'direction' => $this->direction($delta),
                'colors' => $colors,
            ];
            $previous = $percent;
        }

        return $series;
    }

    private function direction(?float $delta): ?string
    {
        return match (true) {
            $delta === null => null,
            $delta > 0 => 'up',
            $delta < 0 => 'down',
            default => 'flat',
        };
    }
}
