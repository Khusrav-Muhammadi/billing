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
        Schema::create('commercial_offer_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_offer_id')->constrained('commercial_offers')->cascadeOnDelete();
            $table->enum('status', ['pending', 'paid', 'canceled'])->index();
            $table->date('status_date')->index();
            $table->enum('payment_method', ['card', 'invoice', 'cash'])->index();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->index(['commercial_offer_id', 'status_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commercial_offer_statuses');
    }
};

