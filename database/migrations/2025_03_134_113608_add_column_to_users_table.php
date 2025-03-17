<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('partner_statuses', function (Blueprint $table) {
            if (!Schema::hasColumn('partner_statuses', 'name')) {
                $table->string('name');
            }
            if (!Schema::hasColumn('partner_statuses', 'connect_amount')) {
                $table->integer('connect_amount');
            }
            if (!Schema::hasColumn('partner_statuses', 'organization_connect_percent')) {
                $table->integer('organization_connect_percent');
            }
            if (!Schema::hasColumn('partner_statuses', 'tariff_price_percent')) {
                $table->integer('tariff_price_percent');
            }
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
