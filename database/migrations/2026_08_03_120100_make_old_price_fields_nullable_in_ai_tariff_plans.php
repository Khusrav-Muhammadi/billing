<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $toNullable = array_values(array_filter(
            ['price_monthly', 'included_limit_balance', 'currency_id'],
            static fn (string $column): bool => Schema::hasColumn('ai_tariff_plans', $column)
        ));

        if ($toNullable === []) {
            return;
        }

        Schema::table('ai_tariff_plans', function (Blueprint $table) use ($toNullable): void {
            if (in_array('price_monthly', $toNullable, true)) {
                $table->decimal('price_monthly', 12, 2)->nullable()->change();
            }
            if (in_array('included_limit_balance', $toNullable, true)) {
                $table->decimal('included_limit_balance', 12, 2)->nullable()->change();
            }
            if (in_array('currency_id', $toNullable, true)) {
                $table->unsignedBigInteger('currency_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // no-op: колонки либо отсутствуют, либо уже nullable по дизайну
    }
};
