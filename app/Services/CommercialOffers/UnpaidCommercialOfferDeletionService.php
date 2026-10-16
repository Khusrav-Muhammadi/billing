<?php

namespace App\Services\CommercialOffers;

use App\Models\CommercialOffer;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UnpaidCommercialOfferDeletionService
{
    /**
     * Удаляет неоплаченное подключение и строки, которые на него ссылаются.
     * Оплаченное подключение не трогаем: по нему уже есть реестры и деньги.
     */
    public function delete(CommercialOffer $offer): void
    {
        if ($offer->isPaid()) {
            throw new RuntimeException('Нельзя удалить оплаченное подключение.');
        }

        DB::transaction(function () use ($offer): void {
            $offerId = (int) $offer->id;
            $paymentId = (int) ($offer->payment_id ?? 0);

            // Сначала дочерние таблицы. Часть связей при удалении КП только обнуляет id,
            // поэтому строки нужно стереть явно, пока id ещё известен.
            foreach ($this->tablesLinkedByOfferId() as $table) {
                DB::table($table)->where('commercial_offer_id', $offerId)->delete();
            }

            $offer->delete();

            $this->deletePaymentIfUnused($paymentId);
        });
    }

    /**
     * Все таблицы текущей базы, где есть commercial_offer_id.
     * Список берём из схемы, чтобы не пропустить новую связанную таблицу.
     *
     * @return list<string>
     */
    private function tablesLinkedByOfferId(): array
    {
        $rows = DB::select(
            'SELECT TABLE_NAME AS table_name
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = ?',
            [DB::getDatabaseName(), 'commercial_offer_id']
        );

        $tables = [];
        foreach ($rows as $row) {
            $name = (string) ($row->table_name ?? $row->TABLE_NAME ?? '');
            if ($name === '' || $name === 'commercial_offers') {
                continue;
            }

            $tables[] = $name;
        }

        return $tables;
    }

    /**
     * Платёж удаляем только если на него больше не ссылается ни одно КП.
     */
    private function deletePaymentIfUnused(int $paymentId): void
    {
        if ($paymentId <= 0) {
            return;
        }

        $stillUsed = DB::table('commercial_offers')
            ->where('payment_id', $paymentId)
            ->exists();

        if ($stillUsed) {
            return;
        }

        DB::table('payment_items')->where('payment_id', $paymentId)->delete();
        DB::table('payments')->where('id', $paymentId)->delete();
    }
}
