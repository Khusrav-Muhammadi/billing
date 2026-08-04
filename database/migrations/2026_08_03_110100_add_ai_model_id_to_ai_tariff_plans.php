<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_tariff_plans', function (Blueprint $table): void {
            $table->unsignedBigInteger('ai_model_id')->nullable()
                ->comment('Ссылка на ai_models');
            $table->foreign('ai_model_id')->references('id')->on('ai_models')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_tariff_plans', function (Blueprint $table): void {
            $table->dropForeign(['ai_model_id']);
            $table->dropColumn('ai_model_id');
        });
    }
};
