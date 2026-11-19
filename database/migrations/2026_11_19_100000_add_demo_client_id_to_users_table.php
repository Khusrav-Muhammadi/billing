<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Партнёрский портал: своё демо shamCRM партнёра (clients.id).
 * Пусто у всех, пока партнёр не создаст или не привяжет демо в кабинете.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'demo_client_id')) {
                $table->unsignedBigInteger('demo_client_id')->nullable()->after('approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'demo_client_id')) {
                $table->dropColumn('demo_client_id');
            }
        });
    }
};
