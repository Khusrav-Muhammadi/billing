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
        Schema::create('discount_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('date');
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('partner_id');
            $table->unsignedInteger('tariff_id');
            $table->decimal('sum');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_expenses');
    }
};
