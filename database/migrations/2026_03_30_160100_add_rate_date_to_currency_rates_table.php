<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('currency_rates') || Schema::hasColumn('currency_rates', 'rate_date')) {
            return;
        }

        Schema::table('currency_rates', function (Blueprint $table) {
            $table->date('rate_date')->nullable()->after('rate');
            $table->index('rate_date');
        });

        DB::table('currency_rates')
            ->whereNull('rate_date')
            ->update(['rate_date' => DB::raw('DATE(created_at)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('currency_rates') || !Schema::hasColumn('currency_rates', 'rate_date')) {
            return;
        }

        Schema::table('currency_rates', function (Blueprint $table) {
            $table->dropIndex(['rate_date']);
            $table->dropColumn('rate_date');
        });
    }
};
