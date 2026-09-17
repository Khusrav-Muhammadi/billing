<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Сколько минут анализа звонков в день даёт тариф.
     * Id планов в проде не меняются: 9 Start, 10 Premium, 11 Vip.
     */
    public function up(): void
    {
        Schema::table('ai_tariff_plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('ai_tariff_plans', 'daily_minutes')) {
                $table->unsignedInteger('daily_minutes')
                    ->nullable()
                    ->after('category')
                    ->comment('Лимит минут анализа звонков в день. Только для call_analyse.');
            }
        });

        // Жёстко по id: эти строки уже в проде и не должны менять идентификаторы.
        DB::table('ai_tariff_plans')->where('id', 9)->update(['daily_minutes' => 30]);
        DB::table('ai_tariff_plans')->where('id', 10)->update(['daily_minutes' => 60]);
        DB::table('ai_tariff_plans')->where('id', 11)->update(['daily_minutes' => 180]);
    }

    public function down(): void
    {
        Schema::table('ai_tariff_plans', function (Blueprint $table): void {
            if (Schema::hasColumn('ai_tariff_plans', 'daily_minutes')) {
                $table->dropColumn('daily_minutes');
            }
        });
    }
};
