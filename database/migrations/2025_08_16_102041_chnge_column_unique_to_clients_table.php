<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            try {
                $table->dropUnique('clients_phone_unique');
            } catch (\Throwable $e) { /* индекс уже снят или назывался иначе */ }

            try {
                $table->unique('email', 'clients_email_unique');
            } catch (\Throwable $e) { /* уже уникален */ }

            try {
                $table->unique('sub_domain', 'clients_sub_domain_unique');
            } catch (\Throwable $e) { /* уже уникален */ }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Вернуть всё назад при откате (по необходимости)
            try {
                $table->dropUnique('clients_email_unique');
            } catch (\Throwable $e) {}

            try {
                $table->dropUnique('clients_sub_domain_unique');
            } catch (\Throwable $e) {}

            try {
                $table->unique('phone', 'clients_phone_unique');
            } catch (\Throwable $e) {}
        });
    }
};
