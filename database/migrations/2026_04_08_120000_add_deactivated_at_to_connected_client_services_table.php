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

        Schema::table('connected_client_services', function (Blueprint $table): void {
            if (!Schema::hasColumn('connected_client_services', 'deactivated_at')) {
                $table->dateTime('deactivated_at')->nullable()->index()->after('status');
            }
        });

        if (!Schema::hasColumn('connected_client_services', 'date')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `connected_client_services` CHANGE COLUMN `date` `date` DATETIME NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('connected_client_services')) {
            return;
        }

        if (Schema::hasColumn('connected_client_services', 'deactivated_at')) {
            Schema::table('connected_client_services', function (Blueprint $table): void {
                $table->dropColumn('deactivated_at');
            });
        }

        if (Schema::hasColumn('connected_client_services', 'date') && DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `connected_client_services` CHANGE COLUMN `date` `date` DATE NULL');
        }
    }
};

