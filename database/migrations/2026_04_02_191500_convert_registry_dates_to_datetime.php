<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeClientPaymentRegistriesDate();

        $this->ensureDateTimeColumn('connected_client_services', 'date');
        $this->ensureDateTimeColumn('discount_expenses', 'date');
        $this->ensureDateTimeColumn('partner_expenses', 'date');
        $this->ensureDateTimeColumn('client_payment_registries', 'date');

        $this->ensureClientBalancesDate();
    }

    public function down(): void
    {
        $this->ensureDateColumn('connected_client_services', 'date');
        $this->ensureDateColumn('discount_expenses', 'date');
        $this->ensureDateColumn('partner_expenses', 'date');

        if (Schema::hasTable('client_payment_registries')) {
            $this->ensureDateColumn('client_payment_registries', 'date');

            if (Schema::hasColumn('client_payment_registries', 'date')
                && !Schema::hasColumn('client_payment_registries', 'offer_date')) {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement('ALTER TABLE `client_payment_registries` CHANGE COLUMN `date` `offer_date` DATE NULL');
                } else {
                    Schema::table('client_payment_registries', function (Blueprint $table): void {
                        $table->date('offer_date')->nullable();
                    });

                    DB::table('client_payment_registries')->update([
                        'offer_date' => DB::raw('date'),
                    ]);

                    Schema::table('client_payment_registries', function (Blueprint $table): void {
                        $table->dropColumn('date');
                    });
                }
            }
        }

        if (Schema::hasTable('client_balances') && Schema::hasColumn('client_balances', 'date')) {
            Schema::table('client_balances', function (Blueprint $table): void {
                $table->dropColumn('date');
            });
        }
    }

    private function normalizeClientPaymentRegistriesDate(): void
    {
        if (!Schema::hasTable('client_payment_registries')) {
            return;
        }

        if (Schema::hasColumn('client_payment_registries', 'offer_date')
            && !Schema::hasColumn('client_payment_registries', 'date')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `client_payment_registries` CHANGE COLUMN `offer_date` `date` DATETIME NULL');
            } else {
                Schema::table('client_payment_registries', function (Blueprint $table): void {
                    $table->dateTime('date')->nullable();
                });

                DB::table('client_payment_registries')->update([
                    'date' => DB::raw('offer_date'),
                ]);

                Schema::table('client_payment_registries', function (Blueprint $table): void {
                    $table->dropColumn('offer_date');
                });
            }
        }

        if (Schema::hasColumn('client_payment_registries', 'offer_date')
            && Schema::hasColumn('client_payment_registries', 'date')) {
            DB::table('client_payment_registries')
                ->whereNull('date')
                ->update(['date' => DB::raw('offer_date')]);

            Schema::table('client_payment_registries', function (Blueprint $table): void {
                $table->dropColumn('offer_date');
            });
        }
    }

    private function ensureClientBalancesDate(): void
    {
        if (!Schema::hasTable('client_balances')) {
            return;
        }

        if (!Schema::hasColumn('client_balances', 'date')) {
            Schema::table('client_balances', function (Blueprint $table): void {
                $table->dateTime('date')->nullable()->index();
            });
        }

        DB::table('client_balances')
            ->whereNull('date')
            ->update(['date' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);

        $this->ensureDateTimeColumn('client_balances', 'date');
    }

    private function ensureDateTimeColumn(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(sprintf('ALTER TABLE `%s` CHANGE COLUMN `%s` `%s` DATETIME NULL', $table, $column, $column));
        }
    }

    private function ensureDateColumn(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(sprintf('ALTER TABLE `%s` CHANGE COLUMN `%s` `%s` DATE NULL', $table, $column, $column));
        }
    }
};
