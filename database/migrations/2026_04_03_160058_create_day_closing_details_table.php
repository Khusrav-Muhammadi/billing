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
        Schema::create('day_closing_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('day_closing_id');
            $table->unsignedBigInteger('organization_id');
            $table->integer('currency_id');
            $table->decimal('balance_before_accrual', 20, 4)->default(0);
            $table->decimal('balance_after_accrual', 20, 4)->default(0);
            $table->boolean('status_after_accrual')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_closing_details');
    }
};
