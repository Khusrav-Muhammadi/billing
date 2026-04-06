<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('partner_expenses')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE `partner_expenses`
                SET `date` = DATE_FORMAT(STR_TO_DATE(`date`, '%Y-%m-%d'), '%Y-%m-%d')
                WHERE `date` IS NOT NULL AND TRIM(`date`) <> ''
            ");

            DB::statement("
                UPDATE `partner_expenses`
                SET `date` = NULL
                WHERE `date` IS NOT NULL AND TRIM(`date`) = ''
            ");

            DB::statement("
                ALTER TABLE `partner_expenses`
                    CHANGE COLUMN `date` `offer_date` DATE NULL,
                    CHANGE COLUMN `sum` `partner_amount` DECIMAL(20,4) NOT NULL DEFAULT 0,
                    ADD COLUMN `client_id` BIGINT UNSIGNED NULL AFTER `partner_id`,
                    ADD COLUMN `service_key` VARCHAR(255) NULL AFTER `service_id`,
                    ADD COLUMN `commercial_offer_id` BIGINT UNSIGNED NULL AFTER `service_key`,
                    ADD COLUMN `commercial_offer_item_id` BIGINT UNSIGNED NULL AFTER `commercial_offer_id`,
                    ADD COLUMN `original_amount` DECIMAL(20,4) NOT NULL DEFAULT 0 AFTER `partner_amount`,
                    ADD COLUMN `partner_percent` DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER `original_amount`,
                    ADD COLUMN `currency_code` VARCHAR(10) NULL AFTER `partner_percent`,
                    ADD COLUMN `request_type` VARCHAR(64) NULL AFTER `currency_code`
            ");

            return;
        }

        Schema::table('partner_expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('partner_expenses', 'offer_date')) {
                $table->date('offer_date')->nullable();
            }
            if (!Schema::hasColumn('partner_expenses', 'partner_amount')) {
                $table->decimal('partner_amount', 20, 4)->default(0);
            }
            if (!Schema::hasColumn('partner_expenses', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable();
            }
            if (!Schema::hasColumn('partner_expenses', 'service_key')) {
                $table->string('service_key')->nullable();
            }
            if (!Schema::hasColumn('partner_expenses', 'commercial_offer_id')) {
                $table->unsignedBigInteger('commercial_offer_id')->nullable();
            }
            if (!Schema::hasColumn('partner_expenses', 'commercial_offer_item_id')) {
                $table->unsignedBigInteger('commercial_offer_item_id')->nullable();
            }
            if (!Schema::hasColumn('partner_expenses', 'original_amount')) {
                $table->decimal('original_amount', 20, 4)->default(0);
            }
            if (!Schema::hasColumn('partner_expenses', 'partner_percent')) {
                $table->decimal('partner_percent', 5, 2)->default(0);
            }
            if (!Schema::hasColumn('partner_expenses', 'currency_code')) {
                $table->string('currency_code', 10)->nullable();
            }
            if (!Schema::hasColumn('partner_expenses', 'request_type')) {
                $table->string('request_type', 64)->nullable();
            }
        });

        if (Schema::hasColumn('partner_expenses', 'sum')
            && Schema::hasColumn('partner_expenses', 'partner_amount')) {
            DB::table('partner_expenses')->update([
                'partner_amount' => DB::raw('sum'),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('partner_expenses')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE `partner_expenses`
                    DROP COLUMN `client_id`,
                    DROP COLUMN `service_key`,
                    DROP COLUMN `commercial_offer_id`,
                    DROP COLUMN `commercial_offer_item_id`,
                    DROP COLUMN `original_amount`,
                    DROP COLUMN `partner_percent`,
                    DROP COLUMN `currency_code`,
                    DROP COLUMN `request_type`,
                    CHANGE COLUMN `offer_date` `date` VARCHAR(255) NULL,
                    CHANGE COLUMN `partner_amount` `sum` DECIMAL(20,4) NOT NULL DEFAULT 0
            ");

            return;
        }

        Schema::table('partner_expenses', function (Blueprint $table) {
            foreach ([
                'offer_date',
                'partner_amount',
                'client_id',
                'service_key',
                'commercial_offer_id',
                'commercial_offer_item_id',
                'original_amount',
                'partner_percent',
                'currency_code',
                'request_type',
            ] as $column) {
                if (Schema::hasColumn('partner_expenses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
