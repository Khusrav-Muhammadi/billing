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

        if (Schema::hasTable('day_closing_details')) {
            if (Schema::hasColumn('day_closing_details', 'balance_before_accrual')) {
                DB::statement('ALTER TABLE `day_closing_details` MODIFY `balance_before_accrual` DECIMAL(20,2) NOT NULL');
            }

            if (Schema::hasColumn('day_closing_details', 'balance_after_accrual')) {
                DB::statement('ALTER TABLE `day_closing_details` MODIFY `balance_after_accrual` DECIMAL(20,2) NOT NULL');
            }

            if (Schema::hasColumn('day_closing_details', 'currency_id')) {
                DB::statement('ALTER TABLE `day_closing_details` MODIFY `currency_id` BIGINT UNSIGNED NULL');
            }
        }

        if (Schema::hasTable('day_closing_client_details')) {
            if (Schema::hasColumn('day_closing_client_details', 'monthly_sum')) {
                DB::statement('ALTER TABLE `day_closing_client_details` MODIFY `monthly_sum` DECIMAL(20,2) NOT NULL');
            }

            if (Schema::hasColumn('day_closing_client_details', 'daily_sum')) {
                DB::statement('ALTER TABLE `day_closing_client_details` MODIFY `daily_sum` DECIMAL(20,2) NOT NULL');
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('day_closing_details')) {
            if (Schema::hasColumn('day_closing_details', 'balance_before_accrual')) {
                DB::statement('ALTER TABLE `day_closing_details` MODIFY `balance_before_accrual` DECIMAL(8,2) NOT NULL');
            }

            if (Schema::hasColumn('day_closing_details', 'balance_after_accrual')) {
                DB::statement('ALTER TABLE `day_closing_details` MODIFY `balance_after_accrual` DECIMAL(8,2) NOT NULL');
            }

            if (Schema::hasColumn('day_closing_details', 'currency_id')) {
                DB::statement('ALTER TABLE `day_closing_details` MODIFY `currency_id` INT NOT NULL');
            }
        }

        if (Schema::hasTable('day_closing_client_details')) {
            if (Schema::hasColumn('day_closing_client_details', 'monthly_sum')) {
                DB::statement('ALTER TABLE `day_closing_client_details` MODIFY `monthly_sum` DECIMAL(4,2) NOT NULL');
            }

            if (Schema::hasColumn('day_closing_client_details', 'daily_sum')) {
                DB::statement('ALTER TABLE `day_closing_client_details` MODIFY `daily_sum` DECIMAL(4,2) NOT NULL');
            }
        }
    }
};

