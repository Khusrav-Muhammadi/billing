<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Some tables can contain legacy NULLs in columns that we want to make NOT NULL.
        // Normalize them to 0 before changing types.
        if (Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'sum_paid_for_license')) {
            DB::statement("UPDATE `organizations` SET `sum_paid_for_license` = 0 WHERE `sum_paid_for_license` IS NULL");
        }

        $this->modifyDecimal('organizations', 'balance', 20, 4, false, '0.0000');
        $this->modifyDecimal('organizations', 'implementation_sum', 20, 4, false, '0.0000');
        $this->modifyDecimal('organizations', 'sum_paid_for_license', 20, 4, false, '0.0000');

        $this->modifyDecimal('clients', 'balance', 20, 4, false, '0.0000');

        foreach (['original_total', 'monthly_total', 'period_total', 'grand_total', 'payable_total'] as $column) {
            $this->modifyDecimal('commercial_offers', $column, 20, 4, false, '0.0000');
        }

        foreach (['unit_price', 'total_price'] as $column) {
            $this->modifyDecimal('commercial_offer_items', $column, 20, 4, false, '0.0000');
        }

        $this->modifyDecimal('connected_client_services', 'service_total_amount', 20, 4, false, '0.0000');
        $this->modifyDecimal('connected_client_services', 'payable_amount', 20, 4, false, '0.0000');

        $this->modifyDecimal('client_balances', 'sum', 20, 4, false, '0.0000');

        foreach (['gross_amount', 'net_amount', 'tariff_amount', 'payment_amount'] as $column) {
            $this->modifyDecimal('client_payment_registries', $column, 20, 4, false, '0.0000');
        }

        $this->modifyDecimal('discount_expenses', 'discount_amount', 20, 4, false, '0.0000');
        $this->modifyDecimal('discount_expenses', 'original_amount', 20, 4, false, '0.0000');

        $this->modifyDecimal('partner_expenses', 'partner_amount', 20, 4, false, '0.0000');
        $this->modifyDecimal('partner_expenses', 'original_amount', 20, 4, false, '0.0000');

        // Day closing tables typically have NOT NULL without explicit defaults.
        $this->modifyDecimal('day_closing_details', 'balance_before_accrual', 20, 4, false, null);
        $this->modifyDecimal('day_closing_details', 'balance_after_accrual', 20, 4, false, null);
        $this->modifyDecimal('day_closing_client_details', 'monthly_sum', 20, 4, false, null);
        $this->modifyDecimal('day_closing_client_details', 'daily_sum', 20, 4, false, null);

        $this->modifyDecimal('payments', 'sum', 20, 4, false, null);
        $this->modifyDecimal('payment_items', 'price', 20, 4, false, null);
        $this->modifyDecimal('invoice_items', 'price', 20, 4, false, null);

        $this->modifyDecimal('prices', 'sum', 20, 4, false, null);
        $this->modifyDecimal('packs', 'price', 20, 4, false, null);

        $this->modifyDecimal('transactions', 'sum', 20, 4, false, null);
        $this->modifyDecimal('transactions', 'accounted_amount', 20, 4, true, null);
        $this->modifyDecimal('sales', 'amount', 20, 4, false, null);

        $this->modifyDecimal('advances', 'sum', 20, 4, false, '0.0000');
        $this->modifyDecimal('incomes', 'sum', 20, 4, false, '0.0000');
    }

    public function down(): void
    {
        // No-op: reverting precision can lose data and depends on legacy column definitions.
    }

    private function modifyDecimal(
        string $table,
        string $column,
        int $precision,
        int $scale,
        bool $nullable,
        ?string $default
    ): void {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $nullSql = $nullable ? 'NULL' : 'NOT NULL';
        $defaultSql = $default !== null ? " DEFAULT {$default}" : '';

        DB::statement(sprintf(
            'ALTER TABLE `%s` MODIFY `%s` DECIMAL(%d,%d) %s%s',
            $table,
            $column,
            $precision,
            $scale,
            $nullSql,
            $defaultSql
        ));
    }
};

