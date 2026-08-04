<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('plan_id')->index();
            $table->boolean('status')->default(true)->index();
            $table->unsignedTinyInteger('period_months');
            $table->decimal('price_paid', 20, 4)->default(0);
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_crm_fetch_at')->nullable();
            $table->unsignedBigInteger('commercial_offer_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('ai_tariff_plans')->restrictOnDelete();
            $table->foreign('commercial_offer_id')->references('id')->on('commercial_offers')->nullOnDelete();

            $table->index(['organization_id', 'status'], 'ai_subscriptions_org_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_subscriptions');
    }
};
