<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Выбор посетителя в конструкторе на сайте (/crm-demo): сфера и размер команды.
 * Передаётся в CRM при создании организации и подставляется в онбординг.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_requests', function (Blueprint $table) {
            $table->json('onboarding')->nullable()->after('manager_id');
        });
    }

    public function down(): void
    {
        Schema::table('demo_requests', function (Blueprint $table) {
            $table->dropColumn('onboarding');
        });
    }
};
