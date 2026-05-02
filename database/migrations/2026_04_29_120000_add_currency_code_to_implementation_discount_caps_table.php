<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('implementation_discount_caps')) {
            return;
        }

        if (!Schema::hasColumn('implementation_discount_caps', 'currency_code')) {
            Schema::table('implementation_discount_caps', function (Blueprint $table) {
                $table->string('currency_code', 10)->nullable()->after('period_type');
                $table->index(['currency_code', 'period_type', 'is_active'], 'impl_discount_caps_currency_period_active_idx');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('implementation_discount_caps')) {
            return;
        }

        if (Schema::hasColumn('implementation_discount_caps', 'currency_code')) {
            Schema::table('implementation_discount_caps', function (Blueprint $table) {
                try {
                    $table->dropIndex('impl_discount_caps_currency_period_active_idx');
                } catch (\Throwable $e) {
                    // ignore
                }
                $table->dropColumn('currency_code');
            });
        }
    }
};

