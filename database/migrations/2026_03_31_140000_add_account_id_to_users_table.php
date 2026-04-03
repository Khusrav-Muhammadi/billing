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
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'account_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('account_id')
                ->nullable()
                ->after('payment_methods')
                ->constrained('accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'account_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });
    }
};

