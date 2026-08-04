<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_tariff_plans', function (Blueprint $table): void {
            // Эти поля устарели — цены вынесены в ai_tariff_plan_prices
            $table->decimal('price_monthly', 12, 2)->nullable()->change();
            $table->decimal('included_limit_balance', 12, 2)->nullable()->change();
            $table->unsignedBigInteger('currency_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ai_tariff_plans', function (Blueprint $table): void {
            $table->decimal('price_monthly', 12, 2)->nullable(false)->change();
            $table->decimal('included_limit_balance', 12, 2)->nullable(false)->change();
            $table->unsignedBigInteger('currency_id')->nullable(false)->change();
        });
    }
};
