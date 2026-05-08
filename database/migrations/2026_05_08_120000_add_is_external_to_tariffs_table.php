<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table): void {
            if (!Schema::hasColumn('tariffs', 'is_external')) {
                $table->boolean('is_external')->default(false)->after('can_increase');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tariffs', function (Blueprint $table): void {
            if (Schema::hasColumn('tariffs', 'is_external')) {
                $table->dropColumn('is_external');
            }
        });
    }
};
