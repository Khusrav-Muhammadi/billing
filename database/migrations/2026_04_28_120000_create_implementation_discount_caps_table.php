<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('implementation_discount_caps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_id')->constrained('tariffs')->cascadeOnDelete();
            $table->string('period_type'); // standard | months_12
            $table->decimal('max_percent', 8, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tariff_id', 'period_type'], 'impl_discount_caps_tariff_period_unique');
            $table->index(['period_type', 'is_active'], 'impl_discount_caps_period_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('implementation_discount_caps');
    }
};

