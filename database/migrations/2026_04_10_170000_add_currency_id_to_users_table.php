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
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'currency_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('currency_id')
                ->nullable()
                ->after('account_id')
                ->constrained('currencies')
                ->nullOnDelete();
        });

        $usdCurrencyId = DB::table('currencies')
            ->whereRaw('UPPER(symbol_code) = ?', ['USD'])
            ->value('id');

        if ($usdCurrencyId) {
            DB::table('users')
                ->whereRaw('LOWER(role) = ?', ['partner'])
                ->whereNull('currency_id')
                ->update(['currency_id' => (int) $usdCurrencyId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'currency_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
        });
    }
};

