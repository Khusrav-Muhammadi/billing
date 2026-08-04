<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_raw_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('crm_log_id');
            $table->string('model_name', 50)->index();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('prompt_cache_hit_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->decimal('calculated_cost', 20, 6)->default(0);
            $table->unsignedBigInteger('cost_currency_id')->nullable()->index();
            $table->decimal('price_per_1m_input_snapshot', 20, 6)->nullable();
            $table->decimal('price_per_1m_output_snapshot', 20, 6)->nullable();
            $table->decimal('margin_percent_snapshot', 5, 2)->nullable();
            $table->boolean('processed')->default(false)->index();
            $table->timestamp('created_at');
            $table->timestamp('fetched_at');

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('cost_currency_id')->references('id')->on('currencies')->nullOnDelete();

            // deduplication: one crm_log_id per organization
            $table->unique(['organization_id', 'crm_log_id'], 'ai_raw_logs_org_crm_unique');

            // fast unprocessed batch fetch
            $table->index(['organization_id', 'processed'], 'ai_raw_logs_org_processed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_raw_logs');
    }
};
