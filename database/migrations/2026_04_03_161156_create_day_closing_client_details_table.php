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
        Schema::create('day_closing_client_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('day_closing_details_id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('tariff_id');
            $table->decimal('monthly_sum', 20, 4)->default(0);
            $table->decimal('daily_sum', 20, 4)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_closing_client_details');
    }
};
