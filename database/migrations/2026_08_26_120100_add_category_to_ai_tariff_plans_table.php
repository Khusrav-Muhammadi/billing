<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_tariff_plans', function (Blueprint $table): void {
            if (!Schema::hasColumn('ai_tariff_plans', 'category')) {
                $table->string('category')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_tariff_plans', function (Blueprint $table): void {
            if (Schema::hasColumn('ai_tariff_plans', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
