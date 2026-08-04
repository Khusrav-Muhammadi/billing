<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tariff_plan_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('plan_id')->index();
            $table->unsignedBigInteger('currency_id');
            $table->decimal('price_monthly', 12, 2)->comment('Цена подписки = сумма зачисляемого лимит-баланса в месяц');
            $table->date('start_date');
            $table->date('end_date')->nullable()->comment('NULL или 9999-12-31 = бессрочно');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('ai_tariff_plans')->cascadeOnDelete();
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['plan_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tariff_plan_prices');
    }
};
