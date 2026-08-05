<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Удаляет legacy ai_token_pricing и старые cost_* на ai_models.
 * Актуальные цены токенов — только ai_model_prices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ai_token_pricing');

        if (Schema::hasTable('ai_models')) {
            $toDrop = [];
            if (Schema::hasColumn('ai_models', 'cost_per_1m_input')) {
                $toDrop[] = 'cost_per_1m_input';
            }
            if (Schema::hasColumn('ai_models', 'cost_per_1m_output')) {
                $toDrop[] = 'cost_per_1m_output';
            }
            if ($toDrop !== []) {
                Schema::table('ai_models', function (Blueprint $table) use ($toDrop): void {
                    $table->dropColumn($toDrop);
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_models')) {
            Schema::table('ai_models', function (Blueprint $table): void {
                if (! Schema::hasColumn('ai_models', 'cost_per_1m_input')) {
                    $table->decimal('cost_per_1m_input', 12, 6)->default(0)->after('provider');
                }
                if (! Schema::hasColumn('ai_models', 'cost_per_1m_output')) {
                    $table->decimal('cost_per_1m_output', 12, 6)->default(0)->after('cost_per_1m_input');
                }
            });
        }

        if (! Schema::hasTable('ai_token_pricing')) {
            Schema::create('ai_token_pricing', function (Blueprint $table): void {
                $table->id();
                $table->string('provider', 50);
                $table->string('model_name', 100)->index();
                $table->unsignedBigInteger('cost_currency_id');
                $table->decimal('cost_per_1m_input', 20, 6)->default(0);
                $table->decimal('cost_per_1m_output', 20, 6)->default(0);
                $table->decimal('margin_percent', 5, 2)->default(0);
                $table->unsignedBigInteger('price_currency_id');
                $table->decimal('price_per_1m_input', 20, 6)->default(0);
                $table->decimal('price_per_1m_output', 20, 6)->default(0);
                $table->timestamp('effective_from')->nullable();
                $table->timestamp('effective_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['model_name', 'effective_to'], 'ai_token_pricing_model_current_idx');
            });
        }
    }
};
