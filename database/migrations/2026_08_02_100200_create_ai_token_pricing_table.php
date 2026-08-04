<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_token_pricing', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 50)->index();
            $table->string('model_name', 50)->index();
            $table->unsignedBigInteger('cost_currency_id')->index();
            $table->decimal('cost_per_1m_input', 20, 6)->default(0);
            $table->decimal('cost_per_1m_output', 20, 6)->default(0);
            $table->decimal('margin_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('price_currency_id')->index();
            $table->decimal('price_per_1m_input', 20, 6)->default(0);
            $table->decimal('price_per_1m_output', 20, 6)->default(0);
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('cost_currency_id')->references('id')->on('currencies')->restrictOnDelete();
            $table->foreign('price_currency_id')->references('id')->on('currencies')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            // fast lookup: current price for a model
            $table->index(['model_name', 'effective_to'], 'ai_token_pricing_model_current_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_token_pricing');
    }
};
