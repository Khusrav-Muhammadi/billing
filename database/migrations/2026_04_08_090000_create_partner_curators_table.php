<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_curators')) {
            return;
        }

        Schema::create('partner_curators', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('partner_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('curator_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['partner_id', 'curator_id']);
            $table->index(['curator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_curators');
    }
};

