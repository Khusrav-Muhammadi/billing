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
        Schema::create('partner_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('partner_id');
            $table->string('date');
            $table->morphs('service');
            $table->decimal('sum');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_expenses');
    }
};
