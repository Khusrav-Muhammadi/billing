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
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'payment_methods')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->json('payment_methods')->nullable()->after('country_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'payment_methods')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('payment_methods');
        });
    }
};

