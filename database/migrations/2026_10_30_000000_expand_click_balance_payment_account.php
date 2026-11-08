<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `click_balance_payments` MODIFY `account` TEXT NOT NULL');
    }

    public function down(): void
    {
        if (DB::table('click_balance_payments')->whereRaw('LENGTH(account) > 9')->exists()) {
            throw new RuntimeException('CLICK account values exceed the previous column length.');
        }

        DB::statement('ALTER TABLE `click_balance_payments` MODIFY `account` VARCHAR(9) NOT NULL');
    }
};
