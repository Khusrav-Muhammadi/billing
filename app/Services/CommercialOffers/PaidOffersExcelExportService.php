<?php

namespace App\Services\CommercialOffers;

use App\Models\CommercialOffer;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaidOffersExcelExportService
{
    private const REQUEST_TYPES = [
        'connection' => 'Подключение',
        'connection_extra_services' => 'Подключение доп услуг',
        'renewal' => 'Продление (изменение)',
        'renewal_no_changes' => 'Продление',
    ];

    private const HEADERS = [
        'Тип подключения',
        'Организация',
        'Дата создания подключения',
        'Дата оплаты',
        'Партнер',
        'Ответственное лицо',
        'Период оплаты',
        'Название услуги',
        'Количество',
        'Цена',
        'Валюта',
        'Месяц',
        'Сумма',
        'Скидка',
        'Сумма со скидкой',
        'Процент партнера',
        'Доля партнера',
    ];

    /** Индексы числовых колонок в полной строке (0-based). */
    private const NUMBER_COLUMN_INDEXES = [8, 9, 11, 12, 13, 14, 15, 16];

    public function download(array $filters = []): StreamedResponse
    {
        $offers = $this->loadPaidOffers($filters);
        $rows = $this->buildRows($offers);
        $filename = 'paid_connections_' . now()->format('Y-m-d_His') . '.xls';

        return response()->streamDownload(function () use ($rows): void {
            echo $this->toSpreadsheetXml($rows);
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private function loadPaidOffers(array $filters): Collection
    {
        $query = CommercialOffer::query()
            ->with([
                'organization:id,name',
                'items.tariff:id,name,is_one_time',
                'aiItem.plan:id,name',
                'offerStatuses' => function ($query) {
                    $query->where('status', 'paid')
                        ->orderByDesc('status_date')
                        ->orderByDesc('id');
                },
                'latestOfferStatus',
            ])
            ->where(function ($query) {
                $query->whereExists(function ($subQuery) {
                    $subQuery->selectRaw('1')
                        ->from('commercial_offer_statuses as paid_latest')
                        ->whereColumn('paid_latest.commercial_offer_id', 'commercial_offers.id')
                        ->where('paid_latest.status', 'paid')
                        ->whereRaw(
                            'paid_latest.id = (
                                select max(cos_max.id)
                                from commercial_offer_statuses as cos_max
                                where cos_max.commercial_offer_id = commercial_offers.id
                            )'
                        );
                })->orWhere(function ($statusQuery) {
                    $statusQuery->where('commercial_offers.status', 'paid')
                        ->whereDoesntHave('offerStatuses');
                });
            });

        app(CommercialOfferListFilter::class)->apply($query, $filters);

        return $query->orderByDesc('id')->get();
    }

    private function buildRows(Collection $offers): array
    {
        $rows = [];

        foreach ($offers as $offer) {
            if (!$this->isPaidOffer($offer)) {
                continue;
            }

            $connection = $this->connectionColumns($offer);
            $itemRows = $this->itemRows($offer);

            if ($itemRows === []) {
                $rows[] = array_merge($connection, $this->emptyItemColumns());
                continue;
            }

            foreach ($itemRows as $itemColumns) {
                $rows[] = array_merge($connection, $itemColumns);
            }
        }

        return $rows;
    }

    private function isPaidOffer(CommercialOffer $offer): bool
    {
        $latestStatus = (string) ($offer->latestOfferStatus?->status ?? '');
        $offerStatus = (string) ($offer->status ?? '');

        return $latestStatus === 'paid'
            || $offerStatus === 'paid'
            || $offer->offerStatuses->isNotEmpty();
    }

    private function connectionColumns(CommercialOffer $offer): array
    {
        $requestType = (string) ($offer->request_type ?: 'connection');
        $paidStatus = $offer->offerStatuses->first();
        $paidAt = $paidStatus?->status_date
            ?? ($offer->latestOfferStatus?->status === 'paid' ? $offer->latestOfferStatus->status_date : null)
            ?? $offer->status_date;

        return [
            self::REQUEST_TYPES[$requestType] ?? ($requestType !== '' ? $requestType : '—'),
            (string) ($offer->organization?->name ?: ($offer->client_name ?: '—')),
            optional($offer->created_at)->format('d.m.Y') ?: '—',
            optional($paidAt)->format('d.m.Y') ?: '—',
            (string) ($offer->partner_name ?: '—'),
            (string) ($offer->manager_name ?: '—'),
            ((int) ($offer->period_months ?? 0)) . ' мес.',
        ];
    }

    private function itemRows(CommercialOffer $offer): array
    {
        $currency = strtoupper((string) ($offer->currency ?: 'USD'));
        $rows = [];

        foreach ($offer->items as $item) {
            $isOneTime = (bool) ($item->tariff?->is_one_time ?? false);
            $quantity = (float) ($item->quantity ?? 0);
            $unitPrice = (float) ($item->unit_price ?? 0);
            $months = $isOneTime ? 1 : max(1, (int) ($item->months ?? 1));
            $discountPercent = (float) ($item->discount_percent ?? 0);
            $partnerPercent = (float) ($item->partner_percent ?? 0);
            $sum = round($quantity * $unitPrice * $months, 4);
            $sumWithDiscount = round((float) ($item->total_price ?? 0), 4);
            if ($sumWithDiscount <= 0 && $sum > 0) {
                $sumWithDiscount = round($sum * (1 - (max(0, min(100, $discountPercent)) / 100)), 4);
            }
            $partnerShare = round($sumWithDiscount * (max(0, min(100, $partnerPercent)) / 100), 4);

            $rows[] = [
                (string) ($item->tariff?->name ?: '—'),
                $quantity,
                $unitPrice,
                $currency,
                $months,
                $sum,
                $discountPercent,
                $sumWithDiscount,
                $partnerPercent,
                $partnerShare,
            ];
        }

        foreach ($this->implementationRows($offer, $currency) as $implementationRow) {
            $rows[] = $implementationRow;
        }

        $aiItem = $offer->aiItem;
        if ($aiItem) {
            $quantity = 1.0;
            $unitPrice = (float) ($aiItem->unit_price ?? 0);
            $months = max(0, (int) ($aiItem->period_months ?? 0));
            $discountPercent = (float) ($aiItem->discount_percent ?? 0);
            $partnerPercent = (float) ($aiItem->partner_percent ?? 0);
            $sum = round((float) ($aiItem->original_price ?? 0), 4);
            if ($sum <= 0) {
                $sum = round($unitPrice * max(1, $months), 4);
            }
            $sumWithDiscount = round($aiItem->chargedTotal(), 4);
            $partnerShare = round($sumWithDiscount * (max(0, min(100, $partnerPercent)) / 100), 4);
            $serviceName = 'ИИ-Агент'
                . ($aiItem->plan?->name ? ': ' . $aiItem->plan->name : '');

            $rows[] = [
                $serviceName,
                $quantity,
                $unitPrice,
                $currency,
                $months,
                $sum,
                $discountPercent,
                $sumWithDiscount,
                $partnerPercent,
                $partnerShare,
            ];
        }

        return $rows;
    }

    /**
     * Разовые платежи внедрения/обучения хранятся в snapshot, не в commercial_offer_items.
     */
    private function implementationRows(CommercialOffer $offer, string $currency): array
    {
        $implementation = $this->resolveImplementation($offer);
        if ($implementation === null) {
            return [];
        }

        $rows = [];
        $enabled = (bool) ($implementation['enabled'] ?? false);
        $basePrice = round(max(0, (float) ($implementation['price'] ?? 0)), 4);
        $discountPercent = round(max(0, min(100, (float) ($implementation['discount_percent'] ?? 0))), 4);

        if ($enabled && $basePrice > 0) {
            $sumWithDiscount = round($basePrice * (1 - ($discountPercent / 100)), 4);
            $rows[] = [
                'Внедрение и обучение',
                1,
                $basePrice,
                $currency,
                1,
                $basePrice,
                $discountPercent,
                $sumWithDiscount,
                0,
                0,
            ];
        }

        $extras = $implementation['extra_services'] ?? [];
        if (is_array($extras)) {
            foreach ($extras as $extra) {
                if (!is_array($extra)) {
                    continue;
                }

                $name = trim((string) ($extra['name'] ?? ''));
                $price = round(max(0, (float) ($extra['price'] ?? 0)), 4);
                if ($name === '' && $price <= 0) {
                    continue;
                }

                $rows[] = [
                    $name !== '' ? $name : 'Доп. разовая услуга',
                    1,
                    $price,
                    $currency,
                    1,
                    $price,
                    0,
                    $price,
                    0,
                    0,
                ];
            }
        }

        return $rows;
    }

    private function resolveImplementation(CommercialOffer $offer): ?array
    {
        $snapshot = $offer->snapshot;
        if (is_string($snapshot)) {
            $snapshot = json_decode($snapshot, true);
        }

        if (!is_array($snapshot)) {
            return null;
        }

        $implementation = $snapshot['implementation'] ?? null;

        return is_array($implementation) ? $implementation : null;
    }

    private function emptyItemColumns(): array
    {
        return ['—', 0, 0, '—', 0, 0, 0, 0, 0, 0];
    }

    private function toSpreadsheetXml(array $rows): string
    {
        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<?mso-application progid="Excel.Sheet"?>';
        $xml[] = '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml[] = ' xmlns:o="urn:schemas-microsoft-com:office:office"';
        $xml[] = ' xmlns:x="urn:schemas-microsoft-com:office:excel"';
        $xml[] = ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml[] = ' xmlns:html="http://www.w3.org/TR/REC-html40">';
        $xml[] = '<Styles>';
        $xml[] = '<Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#E8F5E9" ss:Pattern="Solid"/></Style>';
        $xml[] = '<Style ss:ID="Text"><NumberFormat ss:Format="@"/></Style>';
        $xml[] = '<Style ss:ID="Number"><NumberFormat ss:Format="0.##"/></Style>';
        $xml[] = '</Styles>';
        $xml[] = '<Worksheet ss:Name="Оплаченные подключения">';
        $xml[] = '<Table>';

        $xml[] = '<Row>';
        foreach (self::HEADERS as $header) {
            $xml[] = '<Cell ss:StyleID="Header"><Data ss:Type="String">' . $this->xml($header) . '</Data></Cell>';
        }
        $xml[] = '</Row>';

        foreach ($rows as $row) {
            $xml[] = '<Row>';
            foreach (array_values($row) as $index => $value) {
                $xml[] = $this->cellXml($index, $value);
            }
            $xml[] = '</Row>';
        }

        $xml[] = '</Table>';
        $xml[] = '</Worksheet>';
        $xml[] = '</Workbook>';

        return implode("\n", $xml);
    }

    private function cellXml(int $index, mixed $value): string
    {
        if (in_array($index, self::NUMBER_COLUMN_INDEXES, true) && is_numeric($value)) {
            $number = round((float) $value, 4);

            return '<Cell ss:StyleID="Number"><Data ss:Type="Number">'
                . $this->xml($this->formatExcelNumber($number))
                . '</Data></Cell>';
        }

        return '<Cell ss:StyleID="Text"><Data ss:Type="String">'
            . $this->xml((string) $value)
            . '</Data></Cell>';
    }

    private function formatExcelNumber(float $value): string
    {
        if (abs($value - round($value)) < 0.0000001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
