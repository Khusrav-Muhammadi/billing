<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('currency_id')->index();
            $table->decimal('total_cost', 20, 6)->default(0);
            $table->decimal('deducted_from_limited', 20, 6)->default(0);
            $table->decimal('deducted_from_ai_balance', 20, 6)->default(0);
            $table->timestamp('period_start')->index();
            $table->timestamp('period_end')->index();
            $table->timestamp('created_at');

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('currency_id')->references('id')->on('currencies')->restrictOnDelete();

            $table->index(['organization_id', 'period_start'], 'ai_usage_logs_org_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
