<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );

        return count($result) > 0;
    }

    public function up(): void
    {
        Schema::table('ai_tariff_plan_periods', function (Blueprint $table): void {
            if (!Schema::hasColumn('ai_tariff_plan_periods', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)->default(0)->after('months')
                    ->comment('Скидка за период, %');
            }
            if (!Schema::hasColumn('ai_tariff_plan_periods', 'price_total')) {
                $table->decimal('price_total', 12, 2)->default(0)->after('discount_percent')
                    ->comment('Авто: price_monthly * months * (1 - discount/100)');
            }
            if (!Schema::hasColumn('ai_tariff_plan_periods', 'valid_from')) {
                $table->date('valid_from')->nullable()->after('price_total')
                    ->comment('С какой даты действует скидка');
            }
            if (!Schema::hasColumn('ai_tariff_plan_periods', 'valid_to')) {
                $table->date('valid_to')->nullable()->after('valid_from')
                    ->comment('По какую дату (NULL = действует сейчас)');
            }
            if (!Schema::hasColumn('ai_tariff_plan_periods', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('valid_to');
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            }
        });

        // Убираем старый unique-индекс (plan_id, months) если ещё существует
        if ($this->indexExists('ai_tariff_plan_periods', 'ai_tariff_plan_periods_plan_id_months_unique')) {
            Schema::table('ai_tariff_plan_periods', function (Blueprint $table): void {
                $table->dropUnique(['plan_id', 'months']);
            });
        }

        // Добавляем lookup-индекс если его нет
        if (!$this->indexExists('ai_tariff_plan_periods', 'ai_plan_periods_lookup_idx')) {
            Schema::table('ai_tariff_plan_periods', function (Blueprint $table): void {
                $table->index(['plan_id', 'months', 'valid_to'], 'ai_plan_periods_lookup_idx');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('ai_tariff_plan_periods', 'ai_plan_periods_lookup_idx')) {
            Schema::table('ai_tariff_plan_periods', function (Blueprint $table): void {
                $table->dropIndex('ai_plan_periods_lookup_idx');
            });
        }

        Schema::table('ai_tariff_plan_periods', function (Blueprint $table): void {
            if (Schema::hasColumn('ai_tariff_plan_periods', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
            foreach (['valid_to', 'valid_from', 'price_total', 'discount_percent'] as $col) {
                if (Schema::hasColumn('ai_tariff_plan_periods', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
