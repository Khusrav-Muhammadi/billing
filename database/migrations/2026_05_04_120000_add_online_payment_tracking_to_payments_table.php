<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'transaction_id')) {
                $table->string('transaction_id')->nullable()->after('payment_type')->index();
            }

            if (!Schema::hasColumn('payments', 'provider_status')) {
                $table->string('provider_status')->nullable()->after('transaction_id');
            }

            if (!Schema::hasColumn('payments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('provider_status');
            }

            if (!Schema::hasColumn('payments', 'webhook_payload')) {
                $table->json('webhook_payload')->nullable()->after('paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            foreach (['webhook_payload', 'paid_at', 'provider_status', 'transaction_id'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
