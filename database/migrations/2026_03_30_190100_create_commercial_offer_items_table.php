<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('commercial_offer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_offer_id')->constrained('commercial_offers')->cascadeOnDelete();

            $table->string('service_key')->nullable();
            $table->string('service_name');
            $table->string('billing_type', 32)->default('period');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 20, 4)->default(0);
            $table->unsignedSmallInteger('months')->default(1);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('partner_percent', 5, 2)->default(0);
            $table->decimal('total_price', 20, 4)->default(0);
            $table->json('meta')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commercial_offer_items');
    }
};
