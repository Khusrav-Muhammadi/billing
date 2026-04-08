<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('countries') && !Schema::hasColumn('countries', 'currency_id')) {
            Schema::table('countries', function (Blueprint $table): void {
                $table->unsignedBigInteger('currency_id')->nullable()->index()->after('name');
            });
        }

        // Currency should be resolved via country -> currency, not directly on clients.
        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'currency_id')) {
            Schema::table('clients', function (Blueprint $table): void {
                $table->dropColumn('currency_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clients') && !Schema::hasColumn('clients', 'currency_id')) {
            Schema::table('clients', function (Blueprint $table): void {
                $table->unsignedInteger('currency_id')->nullable();
            });
        }

        if (Schema::hasTable('countries') && Schema::hasColumn('countries', 'currency_id')) {
            Schema::table('countries', function (Blueprint $table): void {
                $table->dropColumn('currency_id');
            });
        }
    }
};

