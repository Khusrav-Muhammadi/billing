<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('licence_paid');
            $table->dropColumn('implementation_sum');
            $table->dropColumn('has_implementation');
            $table->dropColumn('balance');
        });
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn('balance');
            $table->dropColumn('sale_id');
            $table->dropColumn('tariff_id');
        });
        Schema::table('tariffs', function (Blueprint $table): void {
            $table->dropColumn('price');
            $table->dropColumn('sale');
            $table->dropColumn('tariff_id');
        });

        Schema::drop('incomes');
        Schema::drop('package_prices');
        Schema::drop('sales');
        Schema::drop('tariff_currencies');
        Schema::drop('transactions');
        Schema::drop('client_sales');
    }

    public function down(): void
    {

    }
};
