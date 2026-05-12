<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table): void {
            if (!Schema::hasColumn('tariffs', 'one_time_label')) {
                $table->string('one_time_label')->nullable()->after('is_one_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tariffs', function (Blueprint $table): void {
            if (Schema::hasColumn('tariffs', 'one_time_label')) {
                $table->dropColumn('one_time_label');
            }
        });
    }
};
