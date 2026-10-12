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
            if (! Schema::hasColumn('commercial_offer_ai_items', 'implementation_discount_percent')) {
                // Скидка на внедрение ИИ. Без неё при просмотре строка пишется дважды:
                // сохранённая сумма со скидкой и полная цена из прайса.
                $table->decimal('implementation_discount_percent', 8, 4)->default(0)->after('discount_percent');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('commercial_offer_ai_items')) {
            return;
        }

        Schema::table('commercial_offer_ai_items', function (Blueprint $table): void {
            if (Schema::hasColumn('commercial_offer_ai_items', 'implementation_discount_percent')) {
                $table->dropColumn('implementation_discount_percent');
            }
        });
    }
};
