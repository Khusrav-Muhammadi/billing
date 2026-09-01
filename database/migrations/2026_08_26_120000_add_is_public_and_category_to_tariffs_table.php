<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table): void {
            if (!Schema::hasColumn('tariffs', 'is_public')) {
                $table->boolean('is_public')->default(true)->after('is_external');
            }
            if (!Schema::hasColumn('tariffs', 'category')) {
                $table->string('category')->nullable()->after('is_public');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tariffs', function (Blueprint $table): void {
            if (Schema::hasColumn('tariffs', 'category')) {
                $table->dropColumn('category');
            }
            if (Schema::hasColumn('tariffs', 'is_public')) {
                $table->dropColumn('is_public');
            }
        });
    }
};
