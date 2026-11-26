<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payme_balance_transactions', function (Blueprint $table): void {
            if (!Schema::hasColumn('payme_balance_transactions', 'commercial_offer_id')) {
                $table->unsignedBigInteger('commercial_offer_id')->nullable()->after('organization_id')->index();
            }
            if (!Schema::hasColumn('payme_balance_transactions', 'payment_id')) {
                $table->unsignedBigInteger('payment_id')->nullable()->after('commercial_offer_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payme_balance_transactions', function (Blueprint $table): void {
            if (Schema::hasColumn('payme_balance_transactions', 'payment_id')) {
                $table->dropColumn('payment_id');
            }
            if (Schema::hasColumn('payme_balance_transactions', 'commercial_offer_id')) {
                $table->dropColumn('commercial_offer_id');
            }
        });
    }
};
