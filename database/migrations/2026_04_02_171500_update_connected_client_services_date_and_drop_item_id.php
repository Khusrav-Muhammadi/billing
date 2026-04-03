<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('connected_client_services')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            if (Schema::hasColumn('connected_client_services', 'commercial_offer_item_id')) {
                DB::statement('ALTER TABLE `connected_client_services` DROP COLUMN `commercial_offer_item_id`');
            }

            if (Schema::hasColumn('connected_client_services', 'offer_date')
                && !Schema::hasColumn('connected_client_services', 'date')) {
                DB::statement('ALTER TABLE `connected_client_services` CHANGE COLUMN `offer_date` `date` DATE NULL');
            }

            return;
        }

        if (Schema::hasColumn('connected_client_services', 'offer_date')
            && !Schema::hasColumn('connected_client_services', 'date')) {
            Schema::table('connected_client_services', function (Blueprint $table): void {
                $table->date('date')->nullable();
            });

            DB::table('connected_client_services')->update([
                'date' => DB::raw('offer_date'),
            ]);

            Schema::table('connected_client_services', function (Blueprint $table): void {
                $table->dropColumn('offer_date');
            });
        }

        if (Schema::hasColumn('connected_client_services', 'commercial_offer_item_id')) {
            Schema::table('connected_client_services', function (Blueprint $table): void {
                $table->dropColumn('commercial_offer_item_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('connected_client_services')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            if (!Schema::hasColumn('connected_client_services', 'commercial_offer_item_id')) {
                DB::statement('ALTER TABLE `connected_client_services` ADD COLUMN `commercial_offer_item_id` BIGINT UNSIGNED NULL AFTER `commercial_offer_id`');
            }

            if (Schema::hasColumn('connected_client_services', 'date')
                && !Schema::hasColumn('connected_client_services', 'offer_date')) {
                DB::statement('ALTER TABLE `connected_client_services` CHANGE COLUMN `date` `offer_date` DATE NULL');
            }

            return;
        }

        if (Schema::hasColumn('connected_client_services', 'date')
            && !Schema::hasColumn('connected_client_services', 'offer_date')) {
            Schema::table('connected_client_services', function (Blueprint $table): void {
                $table->date('offer_date')->nullable();
            });

            DB::table('connected_client_services')->update([
                'offer_date' => DB::raw('date'),
            ]);

            Schema::table('connected_client_services', function (Blueprint $table): void {
                $table->dropColumn('date');
            });
        }

        if (!Schema::hasColumn('connected_client_services', 'commercial_offer_item_id')) {
            Schema::table('connected_client_services', function (Blueprint $table): void {
                $table->unsignedBigInteger('commercial_offer_item_id')->nullable();
            });
        }
    }
};
