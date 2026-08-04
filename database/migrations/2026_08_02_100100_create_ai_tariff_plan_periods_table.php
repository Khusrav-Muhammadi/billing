<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tariff_plan_periods', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('plan_id')->index();
            $table->unsignedTinyInteger('months')->comment('Длительность периода в месяцах');
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('ai_tariff_plans')->cascadeOnDelete();
            $table->unique(['plan_id', 'months']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tariff_plan_periods');
    }
};
