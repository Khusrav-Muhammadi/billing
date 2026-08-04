<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_balance_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('currency_id')->index();
            $table->string('type', 30)->index();
            $table->string('target_balance', 20);
            $table->decimal('amount', 20, 4);
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('currency_id')->references('id')->on('currencies')->restrictOnDelete();

            $table->index(['organization_id', 'created_at'], 'ai_balance_txn_org_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_balance_transactions');
    }
};
