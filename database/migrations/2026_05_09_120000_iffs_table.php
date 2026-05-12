<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            if (Schema::hasColumn('organizations', 'licence_paid')) {
                $table->dropColumn('licence_paid');
            }

            if (Schema::hasColumn('organizations', 'implementation_sum')) {
                $table->dropColumn('implementation_sum');
            }

            if (Schema::hasColumn('organizations', 'has_implementation')) {
                $table->dropColumn('has_implementation');
            }

            if (Schema::hasColumn('organizations', 'balance')) {
                $table->dropColumn('balance');
            }
        });

        Schema::table('clients', function (Blueprint $table): void {
            if (Schema::hasColumn('clients', 'balance')) {
                $table->dropColumn('balance');
            }

            if (Schema::hasColumn('clients', 'sale_id')) {
                $table->dropColumn('sale_id');
            }

            if (Schema::hasColumn('clients', 'tariff_id')) {
                $table->dropColumn('tariff_id');
            }
        });

        Schema::table('tariffs', function (Blueprint $table): void {
            if (Schema::hasColumn('tariffs', 'price')) {
                $table->dropColumn('price');
            }

            if (Schema::hasColumn('tariffs', 'sale')) {
                $table->dropColumn('sale');
            }

            if (Schema::hasColumn('tariffs', 'tariff_id')) {
                $table->dropColumn('tariff_id');
            }
        });

        Schema::dropIfExists('incomes');
        Schema::dropIfExists('package_prices');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('tariff_currencies');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('client_sales');
    }

    public function down(): void
    {

    }
};
