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
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'status')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->nullable()->after('role');
            $table->index('status');
        });

        DB::table('users')
            ->where('role', 'partner')
            ->whereNull('status')
            ->update(['status' => 'partner']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'status')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};

