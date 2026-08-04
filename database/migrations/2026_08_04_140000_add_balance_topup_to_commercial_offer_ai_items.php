<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('commercial_offer_ai_items')) {
            return;
        }

        Schema::table('commercial_offer_ai_items', function (Blueprint $table): void {
            if (!Schema::hasColumn('commercial_offer_ai_items', 'balance_topup')) {
                $table->decimal('balance_topup', 20, 4)->default(0)->after('total_price')
                    ->comment('Запас на ИИ-баланс (свободный кошелёк), оплачивается отдельно от периода');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('commercial_offer_ai_items')) {
            return;
        }

        Schema::table('commercial_offer_ai_items', function (Blueprint $table): void {
            if (Schema::hasColumn('commercial_offer_ai_items', 'balance_topup')) {
                $table->dropColumn('balance_topup');
            }
        });
    }
};
