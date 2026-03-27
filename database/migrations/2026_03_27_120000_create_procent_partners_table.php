<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procent_partners', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->unsignedInteger('procent_from_tariff')->nullable();
            $table->unsignedInteger('procent_from_pack')->nullable();
            $table->timestamps();

            $table->unique('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procent_partners');
    }
};

