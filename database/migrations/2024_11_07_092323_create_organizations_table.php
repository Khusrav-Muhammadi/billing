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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('INN')->nullable();
            $table->string('phone');
            $table->text('address');
            $table->unsignedInteger('sale_id')->nullable();
            $table->unsignedInteger('client_id')->nullable();
            $table->boolean('has_access')->default(true);
            $table->unsignedInteger('tariff_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
