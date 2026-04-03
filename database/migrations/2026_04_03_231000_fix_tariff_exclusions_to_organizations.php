<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tariff_exclusions')) {
            return;
        }

        if (Schema::hasColumn('tariff_exclusions', 'excluded_tariff_id')) {
            if (DB::getDriverName() === 'mysql') {
                try {
                    DB::statement('ALTER TABLE `tariff_exclusions` DROP INDEX `tariff_exclusions_tariff_id_excluded_tariff_id_unique`');
                } catch (\Throwable $e) {
                }

                try {
                    DB::statement('ALTER TABLE `tariff_exclusions` DROP INDEX `tariff_exclusions_excluded_tariff_id_index`');
                } catch (\Throwable $e) {
                }

                DB::statement('ALTER TABLE `tariff_exclusions` CHANGE `excluded_tariff_id` `organization_id` BIGINT UNSIGNED NOT NULL');
            } else {
                Schema::table('tariff_exclusions', function (Blueprint $table): void {
                    $table->unsignedBigInteger('organization_id')->nullable();
                });

                DB::table('tariff_exclusions')->update([
                    'organization_id' => DB::raw('excluded_tariff_id'),
                ]);

                Schema::table('tariff_exclusions', function (Blueprint $table): void {
                    $table->dropColumn('excluded_tariff_id');
                });
            }
        }

        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE `tariff_exclusions` ADD UNIQUE `tariff_exclusions_tariff_id_organization_id_unique` (`tariff_id`, `organization_id`)');
            } catch (\Throwable $e) {
            }

            try {
                DB::statement('ALTER TABLE `tariff_exclusions` ADD INDEX `tariff_exclusions_organization_id_index` (`organization_id`)');
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('tariff_exclusions')) {
            return;
        }

        if (Schema::hasColumn('tariff_exclusions', 'organization_id')) {
            if (DB::getDriverName() === 'mysql') {
                try {
                    DB::statement('ALTER TABLE `tariff_exclusions` DROP INDEX `tariff_exclusions_tariff_id_organization_id_unique`');
                } catch (\Throwable $e) {
                }

                try {
                    DB::statement('ALTER TABLE `tariff_exclusions` DROP INDEX `tariff_exclusions_organization_id_index`');
                } catch (\Throwable $e) {
                }

                DB::statement('ALTER TABLE `tariff_exclusions` CHANGE `organization_id` `excluded_tariff_id` BIGINT UNSIGNED NOT NULL');
            } else {
                Schema::table('tariff_exclusions', function (Blueprint $table): void {
                    $table->unsignedBigInteger('excluded_tariff_id')->nullable();
                });

                DB::table('tariff_exclusions')->update([
                    'excluded_tariff_id' => DB::raw('organization_id'),
                ]);

                Schema::table('tariff_exclusions', function (Blueprint $table): void {
                    $table->dropColumn('organization_id');
                });
            }
        }
    }
};

