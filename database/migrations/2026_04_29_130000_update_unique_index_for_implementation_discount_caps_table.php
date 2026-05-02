<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // MySQL may "bind" the FK to the old unique index. We have to drop FK first,
        // then rebuild uniques, then restore FK back.
        $droppedForeignKey = false;
        if ($hasTariff) {
            $constraintNames = [];
            try {
                $rows = DB::select("
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'implementation_discount_caps'
                      AND COLUMN_NAME = 'tariff_id'
                      AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                foreach ($rows as $row) {
                    $name = (string)($row->CONSTRAINT_NAME ?? '');
                    if ($name !== '') {
                        $constraintNames[] = $name;
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }

            // Fallback to default Laravel name.
            if (!$constraintNames) {
                $constraintNames = ['implementation_discount_caps_tariff_id_foreign'];
            }

            foreach (array_unique($constraintNames) as $name) {
                try {
                    DB::statement("ALTER TABLE `implementation_discount_caps` DROP FOREIGN KEY `$name`");
                    $droppedForeignKey = true;
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }

        Schema::table('implementation_discount_caps', function (Blueprint $table) use ($hasTariff) {
            // Ensure there is a separate index on tariff_id for the FK.
            try {
                if ($hasTariff) {
                    $table->index(['tariff_id'], 'impl_discount_caps_tariff_idx');
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
        });

        if ($droppedForeignKey) {
            try {
                DB::statement('ALTER TABLE `implementation_discount_caps` ADD CONSTRAINT `implementation_discount_caps_tariff_id_foreign` FOREIGN KEY (`tariff_id`) REFERENCES `tariffs` (`id`) ON DELETE CASCADE');
            } catch (\Throwable $e) {
                // ignore
            }
        }
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

        $droppedForeignKey = false;
        if ($hasTariff) {
            $constraintNames = [];
            try {
                $rows = DB::select("
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'implementation_discount_caps'
                      AND COLUMN_NAME = 'tariff_id'
                      AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                foreach ($rows as $row) {
                    $name = (string)($row->CONSTRAINT_NAME ?? '');
                    if ($name !== '') {
                        $constraintNames[] = $name;
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }

            if (!$constraintNames) {
                $constraintNames = ['implementation_discount_caps_tariff_id_foreign'];
            }

            foreach (array_unique($constraintNames) as $name) {
                try {
                    DB::statement("ALTER TABLE `implementation_discount_caps` DROP FOREIGN KEY `$name`");
                    $droppedForeignKey = true;
                } catch (\Throwable $e) {
                    // ignore
                }
            }
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

        if ($droppedForeignKey) {
            try {
                DB::statement('ALTER TABLE `implementation_discount_caps` ADD CONSTRAINT `implementation_discount_caps_tariff_id_foreign` FOREIGN KEY (`tariff_id`) REFERENCES `tariffs` (`id`) ON DELETE CASCADE');
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
};
