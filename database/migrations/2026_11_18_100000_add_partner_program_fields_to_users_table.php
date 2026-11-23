<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Партнёрская программа (partners.shamcrm.com): саморегистрация с одобрением.
 * Все существующие пользователи получают approval_status = 'approved', поэтому
 * текущие партнёры работают как раньше.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'approval_status')) {
                $table->string('approval_status', 20)->default('approved')->after('status');
            }
            if (!Schema::hasColumn('users', 'registered_via')) {
                $table->string('registered_via', 30)->nullable()->after('approval_status');
            }
            if (!Schema::hasColumn('users', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('registered_via');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['approved_at', 'registered_via', 'approval_status'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
