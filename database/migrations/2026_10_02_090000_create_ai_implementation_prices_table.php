<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_implementation_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('plan_id')->index();
            $table->unsignedBigInteger('currency_id');
            $table->decimal('sum', 15, 2)->default(0);
            $table->date('start_date');
            // NULL или 9999-12-31 — цена без даты окончания.
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('ai_tariff_plans')->cascadeOnDelete();
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->index(['plan_id', 'currency_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_implementation_prices');
    }
};
