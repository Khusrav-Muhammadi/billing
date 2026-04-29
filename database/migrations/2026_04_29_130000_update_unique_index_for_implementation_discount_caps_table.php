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

        $hasTariff = Schema::hasColumn('implementation_discount_caps', 'tariff_id');
        $hasCurrency = Schema::hasColumn('implementation_discount_caps', 'currency_code');

        // If currency is not present yet - nothing to adjust.
        if (!$hasCurrency) {
            return;
        }

        Schema::table('implementation_discount_caps', function (Blueprint $table) use ($hasTariff) {
            // MySQL may rely on the existing unique index (tariff_id, period_type)
            // for the foreign key on tariff_id. Ensure there is a separate index first.
            try {
                if ($hasTariff) {
                    $table->index(['tariff_id'], 'impl_discount_caps_tariff_idx');
                }
            } catch (\Throwable $e) {
                // ignore
            }

            // Prefer creating the new unique first: it will also satisfy the FK index requirement.
            try {
                if ($hasTariff) {
                    $table->unique(
                        ['tariff_id', 'period_type', 'currency_code'],
                        'impl_discount_caps_tariff_period_currency_unique'
                    );
                } else {
                    $table->unique(
                        ['period_type', 'currency_code'],
                        'impl_discount_caps_period_currency_unique'
                    );
                }
            } catch (\Throwable $e) {
                // ignore
            }

            // Old unique (legacy): (tariff_id, period_type)
            try {
                $table->dropUnique('impl_discount_caps_tariff_period_unique');
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('implementation_discount_caps')) {
            return;
        }

        $hasTariff = Schema::hasColumn('implementation_discount_caps', 'tariff_id');
        $hasCurrency = Schema::hasColumn('implementation_discount_caps', 'currency_code');

        if (!$hasCurrency) {
            return;
        }

        Schema::table('implementation_discount_caps', function (Blueprint $table) use ($hasTariff) {
            try {
                $table->dropUnique('impl_discount_caps_tariff_period_currency_unique');
            } catch (\Throwable $e) {
                // ignore
            }
            try {
                $table->dropUnique('impl_discount_caps_period_currency_unique');
            } catch (\Throwable $e) {
                // ignore
            }

            // Restore legacy unique to keep old behavior if needed.
            try {
                if ($hasTariff) {
                    $table->unique(['tariff_id', 'period_type'], 'impl_discount_caps_tariff_period_unique');
                }
            } catch (\Throwable $e) {
                // ignore
            }

            try {
                if ($hasTariff) {
                    $table->dropIndex('impl_discount_caps_tariff_idx');
                }
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }
};
