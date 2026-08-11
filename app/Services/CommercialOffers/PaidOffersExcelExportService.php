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
                'items.tariff:id,name',
                'aiItem.plan:id,name',
                'offerStatuses' => function ($query) {
                    $query->where('status', 'paid')
                        ->orderByDesc('status_date')
                        ->orderByDesc('id');
                },
                'latestOfferStatus',
            ])
            // whereHas(latestOfferStatus) + latestOfMany даёт ambiguous commercial_offer_id
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
            $quantity = (float) ($item->quantity ?? 0);
            $unitPrice = (float) ($item->unit_price ?? 0);
            $months = max(1, (int) ($item->months ?? 1));
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
                $this->formatNumber($quantity),
                $this->formatNumber($unitPrice),
                $currency,
                $months,
                $this->formatNumber($sum),
                $this->formatNumber($discountPercent) . '%',
                $this->formatNumber($sumWithDiscount),
                $this->formatNumber($partnerPercent) . '%',
                $this->formatNumber($partnerShare),
            ];
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
                $this->formatNumber($quantity),
                $this->formatNumber($unitPrice),
                $currency,
                $months,
                $this->formatNumber($sum),
                $this->formatNumber($discountPercent) . '%',
                $this->formatNumber($sumWithDiscount),
                $this->formatNumber($partnerPercent) . '%',
                $this->formatNumber($partnerShare),
            ];
        }

        return $rows;
    }

    private function emptyItemColumns(): array
    {
        return array_fill(0, 10, '—');
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');
        if (str_ends_with($formatted, '.00')) {
            return substr($formatted, 0, -3);
        }

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
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
            foreach ($row as $value) {
                $xml[] = '<Cell ss:StyleID="Text"><Data ss:Type="String">' . $this->xml((string) $value) . '</Data></Cell>';
            }
            $xml[] = '</Row>';
        }

        $xml[] = '</Table>';
        $xml[] = '</Worksheet>';
        $xml[] = '</Workbook>';

        return implode("\n", $xml);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
