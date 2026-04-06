<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('discount_expenses')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE `discount_expenses`
                SET `date` = DATE_FORMAT(STR_TO_DATE(`date`, '%Y-%m-%d'), '%Y-%m-%d')
                WHERE `date` IS NOT NULL AND TRIM(`date`) <> ''
            ");

            DB::statement("
                UPDATE `discount_expenses`
                SET `date` = NULL
                WHERE `date` IS NOT NULL AND TRIM(`date`) = ''
            ");

            DB::statement("
                ALTER TABLE `discount_expenses`
                    CHANGE COLUMN `date` `offer_date` DATE NULL,
                    CHANGE COLUMN `partner_id` `partner_id` INT UNSIGNED NULL,
                    CHANGE COLUMN `sum` `discount_amount` DECIMAL(20,4) NOT NULL DEFAULT 0,
                    ADD COLUMN `service_key` VARCHAR(255) NULL AFTER `tariff_id`,
                    ADD COLUMN `commercial_offer_id` BIGINT UNSIGNED NULL AFTER `service_key`,
                    ADD COLUMN `commercial_offer_item_id` BIGINT UNSIGNED NULL AFTER `commercial_offer_id`,
                    ADD COLUMN `original_amount` DECIMAL(20,4) NOT NULL DEFAULT 0 AFTER `discount_amount`,
                    ADD COLUMN `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER `original_amount`,
                    ADD COLUMN `currency_code` VARCHAR(10) NULL AFTER `discount_percent`
            ");

            return;
        }

        Schema::table('discount_expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('discount_expenses', 'offer_date')) {
                $table->date('offer_date')->nullable();
            }
            if (!Schema::hasColumn('discount_expenses', 'service_key')) {
                $table->string('service_key')->nullable();
            }
            if (!Schema::hasColumn('discount_expenses', 'commercial_offer_id')) {
                $table->unsignedBigInteger('commercial_offer_id')->nullable();
            }
            if (!Schema::hasColumn('discount_expenses', 'commercial_offer_item_id')) {
                $table->unsignedBigInteger('commercial_offer_item_id')->nullable();
            }
            if (!Schema::hasColumn('discount_expenses', 'discount_amount')) {
                $table->decimal('discount_amount', 20, 4)->default(0);
            }
            if (!Schema::hasColumn('discount_expenses', 'original_amount')) {
                $table->decimal('original_amount', 20, 4)->default(0);
            }
            if (!Schema::hasColumn('discount_expenses', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->default(0);
            }
            if (!Schema::hasColumn('discount_expenses', 'currency_code')) {
                $table->string('currency_code', 10)->nullable();
            }
        });

        if (Schema::hasColumn('discount_expenses', 'sum')
            && Schema::hasColumn('discount_expenses', 'discount_amount')) {
            DB::table('discount_expenses')->update([
                'discount_amount' => DB::raw('sum'),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('discount_expenses')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE `discount_expenses`
                    DROP COLUMN `service_key`,
                    DROP COLUMN `commercial_offer_id`,
                    DROP COLUMN `commercial_offer_item_id`,
                    DROP COLUMN `original_amount`,
                    DROP COLUMN `discount_percent`,
                    DROP COLUMN `currency_code`,
                    CHANGE COLUMN `offer_date` `date` VARCHAR(255) NULL,
                    CHANGE COLUMN `partner_id` `partner_id` INT UNSIGNED NOT NULL,
                    CHANGE COLUMN `discount_amount` `sum` DECIMAL(20,4) NOT NULL DEFAULT 0
            ");

            return;
        }

        Schema::table('discount_expenses', function (Blueprint $table) {
            foreach ([
                'offer_date',
                'service_key',
                'commercial_offer_id',
                'commercial_offer_item_id',
                'discount_amount',
                'original_amount',
                'discount_percent',
                'currency_code',
            ] as $column) {
                if (Schema::hasColumn('discount_expenses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
