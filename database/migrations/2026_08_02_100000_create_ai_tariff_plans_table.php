<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tariff_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('model', 100)->nullable()->comment('Модель ИИ, например gpt-4o, claude-3-5-sonnet');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tariff_plans');
    }
};
