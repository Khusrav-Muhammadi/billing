<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('connected_client_services')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE `connected_client_services`
                SET `status` = CASE
                    WHEN LOWER(TRIM(`status`)) IN ('1', 'true', 'paid', 'success', 'successful', 'active')
                        THEN '1'
                    ELSE '0'
                END
            ");

            DB::statement("
                UPDATE `connected_client_services`
                SET `date` = DATE_FORMAT(STR_TO_DATE(`date`, '%Y-%m-%d'), '%Y-%m-%d')
                WHERE `date` IS NOT NULL AND TRIM(`date`) <> ''
            ");

            DB::statement("
                UPDATE `connected_client_services`
                SET `date` = NULL
                WHERE `date` IS NOT NULL AND TRIM(`date`) = ''
            ");

            DB::statement("
                ALTER TABLE `connected_client_services`
                    CHANGE COLUMN `sum` `service_total_amount` DECIMAL(20,2) NOT NULL DEFAULT 0,
                    CHANGE COLUMN `status` `status` TINYINT(1) NOT NULL DEFAULT 1,
                    CHANGE COLUMN `date` `offer_date` DATE NULL,
                    ADD COLUMN `partner_id` BIGINT UNSIGNED NULL AFTER `client_id`,
                    ADD COLUMN `commercial_offer_id` BIGINT UNSIGNED NULL AFTER `tariff_id`,
                    ADD COLUMN `commercial_offer_item_id` BIGINT UNSIGNED NULL AFTER `commercial_offer_id`,
                    ADD COLUMN `account_id` BIGINT UNSIGNED NULL AFTER `commercial_offer_item_id`,
                    ADD COLUMN `offer_currency` VARCHAR(10) NULL AFTER `account_id`,
                    ADD COLUMN `payable_currency` VARCHAR(10) NULL AFTER `offer_currency`,
                    ADD COLUMN `payable_amount` DECIMAL(20,2) NOT NULL DEFAULT 0 AFTER `payable_currency`
            ");

            return;
        }

        Schema::table('connected_client_services', function (Blueprint $table) {
            if (!Schema::hasColumn('connected_client_services', 'partner_id')) {
                $table->unsignedBigInteger('partner_id')->nullable();
            }
            if (!Schema::hasColumn('connected_client_services', 'commercial_offer_id')) {
                $table->unsignedBigInteger('commercial_offer_id')->nullable();
            }
            if (!Schema::hasColumn('connected_client_services', 'commercial_offer_item_id')) {
                $table->unsignedBigInteger('commercial_offer_item_id')->nullable();
            }
            if (!Schema::hasColumn('connected_client_services', 'account_id')) {
                $table->unsignedBigInteger('account_id')->nullable();
            }
            if (!Schema::hasColumn('connected_client_services', 'service_total_amount')) {
                $table->decimal('service_total_amount', 20, 2)->default(0);
            }
            if (!Schema::hasColumn('connected_client_services', 'offer_date')) {
                $table->date('offer_date')->nullable();
            }
            if (!Schema::hasColumn('connected_client_services', 'offer_currency')) {
                $table->string('offer_currency', 10)->nullable();
            }
            if (!Schema::hasColumn('connected_client_services', 'payable_currency')) {
                $table->string('payable_currency', 10)->nullable();
            }
            if (!Schema::hasColumn('connected_client_services', 'payable_amount')) {
                $table->decimal('payable_amount', 20, 2)->default(0);
            }
        });

        if (Schema::hasColumn('connected_client_services', 'sum')
            && Schema::hasColumn('connected_client_services', 'service_total_amount')) {
            DB::table('connected_client_services')->update([
                'service_total_amount' => DB::raw('sum'),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('connected_client_services')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE `connected_client_services`
                    DROP COLUMN `partner_id`,
                    DROP COLUMN `commercial_offer_id`,
                    DROP COLUMN `commercial_offer_item_id`,
                    DROP COLUMN `account_id`,
                    DROP COLUMN `offer_currency`,
                    DROP COLUMN `payable_currency`,
                    DROP COLUMN `payable_amount`,
                    CHANGE COLUMN `service_total_amount` `sum` DECIMAL(20,2) NOT NULL DEFAULT 0,
                    CHANGE COLUMN `status` `status` VARCHAR(255) NOT NULL DEFAULT '1',
                    CHANGE COLUMN `offer_date` `date` VARCHAR(255) NULL
            ");

            return;
        }

        Schema::table('connected_client_services', function (Blueprint $table) {
            foreach ([
                'partner_id',
                'commercial_offer_id',
                'commercial_offer_item_id',
                'account_id',
                'service_total_amount',
                'offer_date',
                'offer_currency',
                'payable_currency',
                'payable_amount',
            ] as $column) {
                if (Schema::hasColumn('connected_client_services', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
