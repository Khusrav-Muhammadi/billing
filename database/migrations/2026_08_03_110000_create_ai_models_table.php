<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->comment('Название модели, например gpt-4o');
            $table->enum('provider', ['openai', 'deepseek', 'gemini', 'claude'])->index();
            $table->string('api_key', 500)->nullable()->comment('API-ключ провайдера');
            $table->decimal('cost_per_1m_input', 12, 6)->default(0)->comment('Себестоимость: вход за 1M токенов');
            $table->decimal('cost_per_1m_output', 12, 6)->default(0)->comment('Себестоимость: выход за 1M токенов');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
