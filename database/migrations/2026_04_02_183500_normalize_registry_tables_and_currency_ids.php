<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeClientBalances();
        $this->normalizeDiscountExpenses();
        $this->normalizePartnerExpenses();
        $this->normalizeConnectedClientServicesCurrencies();
        $this->normalizeClientPaymentRegistriesCurrencies();
    }

    public function down(): void
    {
        $this->rollbackClientBalances();
        $this->rollbackDiscountExpenses();
        $this->rollbackPartnerExpenses();
        $this->rollbackConnectedClientServicesCurrencies();
        $this->rollbackClientPaymentRegistriesCurrencies();
    }

    private function normalizeClientBalances(): void
    {
        if (!Schema::hasTable('client_balances')) {
            return;
        }

        if (!Schema::hasColumn('client_balances', 'currency_id')) {
            Schema::table('client_balances', function (Blueprint $table): void {
                $table->unsignedBigInteger('currency_id')->nullable()->after('sum')->index();
            });
        }

        if (Schema::hasColumn('client_balances', 'currency')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("
                    UPDATE `client_balances` cb
                    LEFT JOIN `currencies` c
                      ON UPPER(TRIM(c.`symbol_code`)) = UPPER(TRIM(cb.`currency`))
                    SET cb.`currency_id` = c.`id`
                    WHERE cb.`currency_id` IS NULL
                ");
            } else {
                $currencyMap = DB::table('currencies')
                    ->get(['id', 'symbol_code'])
                    ->mapWithKeys(fn ($row) => [strtoupper(trim((string) $row->symbol_code)) => (int) $row->id])
                    ->all();

                DB::table('client_balances')
                    ->select('id', 'currency')
                    ->orderBy('id')
                    ->chunkById(500, function ($rows) use ($currencyMap): void {
                        foreach ($rows as $row) {
                            $code = strtoupper(trim((string) $row->currency));
                            $currencyId = $currencyMap[$code] ?? null;
                            if (!$currencyId) {
                                continue;
                            }

                            DB::table('client_balances')
                                ->where('id', (int) $row->id)
                                ->update(['currency_id' => $currencyId]);
                        }
                    });
            }

            Schema::table('client_balances', function (Blueprint $table): void {
                $table->dropColumn('currency');
            });
        }

        if (Schema::hasColumn('client_balances', 'commercial_offer_id')) {
            if (DB::getDriverName() === 'mysql') {
                try {
                    DB::statement('ALTER TABLE `client_balances` DROP INDEX `client_balances_commercial_offer_id_unique`');
                } catch (\Throwable $e) {
                    // ignore: index may not exist
                }
            }

            Schema::table('client_balances', function (Blueprint $table): void {
                $table->dropColumn('commercial_offer_id');
            });
        }
    }

    private function normalizeDiscountExpenses(): void
    {
        if (!Schema::hasTable('discount_expenses')) {
            return;
        }

        if (Schema::hasColumn('discount_expenses', 'offer_date') && !Schema::hasColumn('discount_expenses', 'date')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `discount_expenses` CHANGE COLUMN `offer_date` `date` DATE NULL');
            } else {
                Schema::table('discount_expenses', function (Blueprint $table): void {
                    $table->date('date')->nullable();
                });

                DB::table('discount_expenses')->update([
                    'date' => DB::raw('offer_date'),
                ]);

                Schema::table('discount_expenses', function (Blueprint $table): void {
                    $table->dropColumn('offer_date');
                });
            }
        }

        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('discount_expenses', 'date')) {
            DB::statement('ALTER TABLE `discount_expenses` CHANGE COLUMN `date` `date` DATE NULL');
        }

        if (!Schema::hasColumn('discount_expenses', 'currency_id')) {
            Schema::table('discount_expenses', function (Blueprint $table): void {
                $table->unsignedBigInteger('currency_id')->nullable()->after('discount_percent')->index();
            });
        }

        if (Schema::hasColumn('discount_expenses', 'currency_code')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("
                    UPDATE `discount_expenses` de
                    LEFT JOIN `currencies` c
                      ON UPPER(TRIM(c.`symbol_code`)) = UPPER(TRIM(de.`currency_code`))
                    SET de.`currency_id` = c.`id`
                    WHERE de.`currency_id` IS NULL
                ");
            } else {
                $currencyMap = DB::table('currencies')
                    ->get(['id', 'symbol_code'])
                    ->mapWithKeys(fn ($row) => [strtoupper(trim((string) $row->symbol_code)) => (int) $row->id])
                    ->all();

                DB::table('discount_expenses')
                    ->select('id', 'currency_code')
                    ->orderBy('id')
                    ->chunkById(500, function ($rows) use ($currencyMap): void {
                        foreach ($rows as $row) {
                            $code = strtoupper(trim((string) $row->currency_code));
                            $currencyId = $currencyMap[$code] ?? null;
                            if (!$currencyId) {
                                continue;
                            }

                            DB::table('discount_expenses')
                                ->where('id', (int) $row->id)
                                ->update(['currency_id' => $currencyId]);
                        }
                    });
            }
        }

        Schema::table('discount_expenses', function (Blueprint $table): void {
            $dropColumns = [];
            foreach (['service_key', 'commercial_offer_id', 'commercial_offer_item_id', 'offer_date', 'currency_code'] as $column) {
                if (Schema::hasColumn('discount_expenses', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }

    private function normalizePartnerExpenses(): void
    {
        if (!Schema::hasTable('partner_expenses')) {
            return;
        }

        if (Schema::hasColumn('partner_expenses', 'offer_date') && !Schema::hasColumn('partner_expenses', 'date')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `partner_expenses` CHANGE COLUMN `offer_date` `date` DATE NULL');
            } else {
                Schema::table('partner_expenses', function (Blueprint $table): void {
                    $table->date('date')->nullable();
                });

                DB::table('partner_expenses')->update([
                    'date' => DB::raw('offer_date'),
                ]);

                Schema::table('partner_expenses', function (Blueprint $table): void {
                    $table->dropColumn('offer_date');
                });
            }
        }

        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('partner_expenses', 'date')) {
            DB::statement('ALTER TABLE `partner_expenses` CHANGE COLUMN `date` `date` DATE NULL');
        }

        if (!Schema::hasColumn('partner_expenses', 'tariff_id')) {
            Schema::table('partner_expenses', function (Blueprint $table): void {
                $table->unsignedBigInteger('tariff_id')->nullable()->after('client_id')->index();
            });
        }

        if (!Schema::hasColumn('partner_expenses', 'currency_id')) {
            Schema::table('partner_expenses', function (Blueprint $table): void {
                $table->unsignedBigInteger('currency_id')->nullable()->after('partner_percent')->index();
            });
        }

        if (DB::getDriverName() === 'mysql') {
            if (Schema::hasColumn('partner_expenses', 'service_key')) {
                DB::statement("
                    UPDATE `partner_expenses`
                    SET `tariff_id` = CAST(SUBSTRING_INDEX(`service_key`, '-', -1) AS UNSIGNED)
                    WHERE (`tariff_id` IS NULL OR `tariff_id` = 0)
                      AND `service_key` REGEXP '^(tariff|service)-[0-9]+'
                ");
            }

            if (Schema::hasColumn('partner_expenses', 'commercial_offer_item_id')
                && Schema::hasTable('commercial_offer_items')) {
                DB::statement("
                    UPDATE `partner_expenses` pe
                    INNER JOIN `commercial_offer_items` coi
                        ON coi.`id` = pe.`commercial_offer_item_id`
                    SET pe.`tariff_id` = coi.`tariff_id`
                    WHERE (pe.`tariff_id` IS NULL OR pe.`tariff_id` = 0)
                      AND pe.`commercial_offer_item_id` IS NOT NULL
                ");
            }
        }

        if (Schema::hasColumn('partner_expenses', 'currency_code')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("
                    UPDATE `partner_expenses` pe
                    LEFT JOIN `currencies` c
                      ON UPPER(TRIM(c.`symbol_code`)) = UPPER(TRIM(pe.`currency_code`))
                    SET pe.`currency_id` = c.`id`
                    WHERE pe.`currency_id` IS NULL
                ");
            } else {
                $currencyMap = DB::table('currencies')
                    ->get(['id', 'symbol_code'])
                    ->mapWithKeys(fn ($row) => [strtoupper(trim((string) $row->symbol_code)) => (int) $row->id])
                    ->all();

                DB::table('partner_expenses')
                    ->select('id', 'currency_code')
                    ->orderBy('id')
                    ->chunkById(500, function ($rows) use ($currencyMap): void {
                        foreach ($rows as $row) {
                            $code = strtoupper(trim((string) $row->currency_code));
                            $currencyId = $currencyMap[$code] ?? null;
                            if (!$currencyId) {
                                continue;
                            }

                            DB::table('partner_expenses')
                                ->where('id', (int) $row->id)
                                ->update(['currency_id' => $currencyId]);
                        }
                    });
            }
        }

        Schema::table('partner_expenses', function (Blueprint $table): void {
            $dropColumns = [];
            foreach (['service_type', 'service_id', 'service_key', 'commercial_offer_id', 'commercial_offer_item_id', 'offer_date', 'currency_code'] as $column) {
                if (Schema::hasColumn('partner_expenses', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }

    private function normalizeConnectedClientServicesCurrencies(): void
    {
        if (!Schema::hasTable('connected_client_services')) {
            return;
        }

        if (Schema::hasColumn('connected_client_services', 'offer_date') && !Schema::hasColumn('connected_client_services', 'date')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE `connected_client_services` CHANGE COLUMN `offer_date` `date` DATE NULL');
            } else {
                Schema::table('connected_client_services', function (Blueprint $table): void {
                    $table->date('date')->nullable();
                });

                DB::table('connected_client_services')->update([
                    'date' => DB::raw('offer_date'),
                ]);

                Schema::table('connected_client_services', function (Blueprint $table): void {
                    $table->dropColumn('offer_date');
                });
            }
        }

        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('connected_client_services', 'date')) {
            DB::statement('ALTER TABLE `connected_client_services` CHANGE COLUMN `date` `date` DATE NULL');
        }

        if (!Schema::hasColumn('connected_client_services', 'offer_currency_id')) {
            Schema::table('connected_client_services', function (Blueprint $table): void {
                $table->unsignedBigInteger('offer_currency_id')->nullable()->after('date')->index();
            });
        }

        if (!Schema::hasColumn('connected_client_services', 'payable_currency_id')) {
            Schema::table('connected_client_services', function (Blueprint $table): void {
                $table->unsignedBigInteger('payable_currency_id')->nullable()->after('offer_currency_id')->index();
            });
        }

        if (Schema::hasColumn('connected_client_services', 'offer_currency') && DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE `connected_client_services` ccs
                LEFT JOIN `currencies` c
                  ON UPPER(TRIM(c.`symbol_code`)) = UPPER(TRIM(ccs.`offer_currency`))
                SET ccs.`offer_currency_id` = c.`id`
                WHERE ccs.`offer_currency_id` IS NULL
            ");
        }

        if (Schema::hasColumn('connected_client_services', 'payable_currency') && DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE `connected_client_services` ccs
                LEFT JOIN `currencies` c
                  ON UPPER(TRIM(c.`symbol_code`)) = UPPER(TRIM(ccs.`payable_currency`))
                SET ccs.`payable_currency_id` = c.`id`
                WHERE ccs.`payable_currency_id` IS NULL
            ");
        }

        Schema::table('connected_client_services', function (Blueprint $table): void {
            $dropColumns = [];
            foreach (['offer_currency', 'payable_currency', 'offer_date'] as $column) {
                if (Schema::hasColumn('connected_client_services', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }

    private function normalizeClientPaymentRegistriesCurrencies(): void
    {
        if (!Schema::hasTable('client_payment_registries')) {
            return;
        }

        if (!Schema::hasColumn('client_payment_registries', 'tariff_currency_id')) {
            Schema::table('client_payment_registries', function (Blueprint $table): void {
                $table->unsignedBigInteger('tariff_currency_id')->nullable()->after('net_amount')->index();
            });
        }

        if (!Schema::hasColumn('client_payment_registries', 'payment_currency_id')) {
            Schema::table('client_payment_registries', function (Blueprint $table): void {
                $table->unsignedBigInteger('payment_currency_id')->nullable()->after('tariff_amount')->index();
            });
        }

        if (Schema::hasColumn('client_payment_registries', 'tariff_currency') && DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE `client_payment_registries` cpr
                LEFT JOIN `currencies` c
                  ON UPPER(TRIM(c.`symbol_code`)) = UPPER(TRIM(cpr.`tariff_currency`))
                SET cpr.`tariff_currency_id` = c.`id`
                WHERE cpr.`tariff_currency_id` IS NULL
            ");
        }

        if (Schema::hasColumn('client_payment_registries', 'payment_currency') && DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE `client_payment_registries` cpr
                LEFT JOIN `currencies` c
                  ON UPPER(TRIM(c.`symbol_code`)) = UPPER(TRIM(cpr.`payment_currency`))
                SET cpr.`payment_currency_id` = c.`id`
                WHERE cpr.`payment_currency_id` IS NULL
            ");
        }

        Schema::table('client_payment_registries', function (Blueprint $table): void {
            $dropColumns = [];
            foreach (['tariff_currency', 'payment_currency'] as $column) {
                if (Schema::hasColumn('client_payment_registries', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }

    private function rollbackClientBalances(): void
    {
        if (!Schema::hasTable('client_balances')) {
            return;
        }

        if (!Schema::hasColumn('client_balances', 'commercial_offer_id')) {
            Schema::table('client_balances', function (Blueprint $table): void {
                $table->unsignedBigInteger('commercial_offer_id')->nullable()->unique();
            });
        }

        if (!Schema::hasColumn('client_balances', 'currency')) {
            Schema::table('client_balances', function (Blueprint $table): void {
                $table->string('currency', 10)->nullable();
            });
        }

        if (Schema::hasColumn('client_balances', 'currency_id')) {
            Schema::table('client_balances', function (Blueprint $table): void {
                $table->dropColumn('currency_id');
            });
        }
    }

    private function rollbackDiscountExpenses(): void
    {
        if (!Schema::hasTable('discount_expenses')) {
            return;
        }

        if (!Schema::hasColumn('discount_expenses', 'offer_date')) {
            Schema::table('discount_expenses', function (Blueprint $table): void {
                $table->date('offer_date')->nullable();
            });

            if (Schema::hasColumn('discount_expenses', 'date')) {
                DB::table('discount_expenses')->update([
                    'offer_date' => DB::raw('date'),
                ]);
            }
        }

        foreach (['service_key', 'commercial_offer_id', 'commercial_offer_item_id', 'currency_code'] as $column) {
            if (!Schema::hasColumn('discount_expenses', $column)) {
                Schema::table('discount_expenses', function (Blueprint $table) use ($column): void {
                    if ($column === 'service_key') {
                        $table->string('service_key')->nullable();
                    } elseif ($column === 'currency_code') {
                        $table->string('currency_code', 10)->nullable();
                    } else {
                        $table->unsignedBigInteger($column)->nullable();
                    }
                });
            }
        }

        if (Schema::hasColumn('discount_expenses', 'currency_id')) {
            Schema::table('discount_expenses', function (Blueprint $table): void {
                $table->dropColumn('currency_id');
            });
        }
    }

    private function rollbackPartnerExpenses(): void
    {
        if (!Schema::hasTable('partner_expenses')) {
            return;
        }

        if (!Schema::hasColumn('partner_expenses', 'offer_date')) {
            Schema::table('partner_expenses', function (Blueprint $table): void {
                $table->date('offer_date')->nullable();
            });

            if (Schema::hasColumn('partner_expenses', 'date')) {
                DB::table('partner_expenses')->update([
                    'offer_date' => DB::raw('date'),
                ]);
            }
        }

        foreach (['service_type', 'service_id', 'service_key', 'commercial_offer_id', 'commercial_offer_item_id', 'currency_code'] as $column) {
            if (!Schema::hasColumn('partner_expenses', $column)) {
                Schema::table('partner_expenses', function (Blueprint $table) use ($column): void {
                    if ($column === 'service_type' || $column === 'service_key') {
                        $table->string($column)->nullable();
                    } elseif ($column === 'currency_code') {
                        $table->string('currency_code', 10)->nullable();
                    } else {
                        $table->unsignedBigInteger($column)->nullable();
                    }
                });
            }
        }

        foreach (['tariff_id', 'currency_id'] as $column) {
            if (Schema::hasColumn('partner_expenses', $column)) {
                Schema::table('partner_expenses', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function rollbackConnectedClientServicesCurrencies(): void
    {
        if (!Schema::hasTable('connected_client_services')) {
            return;
        }

        foreach (['offer_currency', 'payable_currency'] as $column) {
            if (!Schema::hasColumn('connected_client_services', $column)) {
                Schema::table('connected_client_services', function (Blueprint $table) use ($column): void {
                    $table->string($column, 10)->nullable();
                });
            }
        }

        foreach (['offer_currency_id', 'payable_currency_id'] as $column) {
            if (Schema::hasColumn('connected_client_services', $column)) {
                Schema::table('connected_client_services', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function rollbackClientPaymentRegistriesCurrencies(): void
    {
        if (!Schema::hasTable('client_payment_registries')) {
            return;
        }

        foreach (['tariff_currency', 'payment_currency'] as $column) {
            if (!Schema::hasColumn('client_payment_registries', $column)) {
                Schema::table('client_payment_registries', function (Blueprint $table) use ($column): void {
                    $table->string($column, 10)->nullable();
                });
            }
        }

        foreach (['tariff_currency_id', 'payment_currency_id'] as $column) {
            if (Schema::hasColumn('client_payment_registries', $column)) {
                Schema::table('client_payment_registries', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
