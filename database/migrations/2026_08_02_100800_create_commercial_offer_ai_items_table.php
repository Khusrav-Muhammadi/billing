<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_offer_ai_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('commercial_offer_id')->unique();
            $table->unsignedBigInteger('plan_id')->index();
            $table->unsignedTinyInteger('period_months');
            $table->decimal('unit_price', 20, 4)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('partner_percent', 5, 2)->default(0);
            $table->decimal('original_price', 20, 4)->default(0);
            $table->decimal('total_price', 20, 4)->default(0);
            $table->timestamps();

            $table->foreign('commercial_offer_id')->references('id')->on('commercial_offers')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('ai_tariff_plans')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_offer_ai_items');
    }
};
