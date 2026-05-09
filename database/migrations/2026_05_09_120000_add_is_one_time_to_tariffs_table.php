<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table): void {
            if (!Schema::hasColumn('tariffs', 'is_one_time')) {
                $table->boolean('is_one_time')->default(false)->after('is_external');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tariffs', function (Blueprint $table): void {
            if (Schema::hasColumn('tariffs', 'is_one_time')) {
                $table->dropColumn('is_one_time');
            }
        });
    }
};
