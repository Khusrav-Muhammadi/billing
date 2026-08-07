<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commercial_offer_ai_items')) {
            return;
        }

        Schema::table('commercial_offer_ai_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('commercial_offer_ai_items', 'current_month_amount')) {
                $table->decimal('current_month_amount', 20, 4)->default(0)->after('total_price')
                    ->comment('Пропорция текущего месяца (readonly в КП)');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('commercial_offer_ai_items')) {
            return;
        }

        Schema::table('commercial_offer_ai_items', function (Blueprint $table): void {
            if (Schema::hasColumn('commercial_offer_ai_items', 'current_month_amount')) {
                $table->dropColumn('current_month_amount');
            }
        });
    }
};
