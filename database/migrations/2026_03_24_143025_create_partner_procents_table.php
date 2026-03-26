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
        Schema::create('partner_procents', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('partner_id');
            $table->string('date');
            $table->unsignedInteger('procent_from_tariff')->nullable();
            $table->unsignedInteger('procent_from_pack')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_procents');
    }
};
