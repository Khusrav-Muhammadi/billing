<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_model_prices')) {
            return;
        }

        Schema::create('ai_model_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ai_model_id')->index();
            $table->unsignedBigInteger('currency_id')->index();
            $table->decimal('price_per_1m_input', 20, 6)->default(0)->comment('Цена входных токенов за 1M');
            $table->decimal('price_per_1m_output', 20, 6)->default(0)->comment('Цена выходных токенов за 1M');
            $table->date('start_date');
            $table->date('end_date')->nullable()->comment('NULL или 9999-12-31 = бессрочно');
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('ai_model_id')->references('id')->on('ai_models')->cascadeOnDelete();
            $table->foreign('currency_id')->references('id')->on('currencies')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['ai_model_id', 'currency_id', 'start_date'], 'ai_model_prices_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_prices');
    }
};
