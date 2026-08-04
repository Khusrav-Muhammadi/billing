<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_balances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->unique();
            $table->unsignedBigInteger('currency_id')->index();
            $table->decimal('limited_balance', 20, 4)->default(0);
            $table->decimal('ai_balance', 20, 4)->default(0);
            $table->boolean('is_agent_enabled')->default(false)->index();
            $table->date('scheduled_activation_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('currency_id')->references('id')->on('currencies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_balances');
    }
};
