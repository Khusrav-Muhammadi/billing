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
        Schema::create('package_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('pack_id');
            $table->unsignedInteger('tariff_id');
            $table->unsignedInteger('currency_id');
            $table->unsignedInteger('one_time_price');
            $table->unsignedInteger('monthly_price');
            $table->boolean('is_included');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_prices');
    }
};
