<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('connected_client_services')) {
            return;
        }

        if (!Schema::hasColumn('connected_client_services', 'quantity')) {
            Schema::table('connected_client_services', function (Blueprint $table): void {
                $table->unsignedInteger('quantity')->default(1)->after('tariff_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('connected_client_services')) {
            return;
        }

        if (Schema::hasColumn('connected_client_services', 'quantity')) {
            Schema::table('connected_client_services', function (Blueprint $table): void {
                $table->dropColumn('quantity');
            });
        }
    }
};

