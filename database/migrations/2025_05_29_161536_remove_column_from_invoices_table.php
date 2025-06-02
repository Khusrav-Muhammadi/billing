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
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('receipt');
            $table->dropColumn('cancel_url');
            $table->dropColumn('redirect_url');
            $table->dropColumn('webhook_url');
            $table->dropColumn('timeout');
            $table->dropColumn('invoice_id');
            $table->dropColumn('invoice_status_id');
            $table->dropColumn('phone');

            $table->string('payment_provider_id')->nullable();
            $table->string('status');
            $table->string('email');
            $table->unsignedBigInteger('total_amount');
            $table->integer('currency_id');
            $table->string('provider');
            $table->string('operation_type');
            $table->json('additional_data')->nullable();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payment_provider_id')->nullable()->change();
        });
    }
};
