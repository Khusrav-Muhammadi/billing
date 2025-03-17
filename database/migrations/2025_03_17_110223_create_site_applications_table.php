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
        Schema::create('site_applications', function (Blueprint $table) {
            $table->id();
            $table->string('fio');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('organization')->nullable();
            $table->string('region')->nullable();
            $table->string('request_type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_applications');
    }
};
