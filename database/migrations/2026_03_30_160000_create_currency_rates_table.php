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
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('base_currency_id');
            $table->unsignedBigInteger('quote_currency_id');
            $table->decimal('rate', 20, 6);
            $table->timestamps();

            $table->index('base_currency_id');
            $table->index('quote_currency_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
