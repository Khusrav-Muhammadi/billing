<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->boolean('is_extra_user')->default(false)->after('is_tariff');
            $table->unsignedBigInteger('parent_tariff_id')->nullable()->after('is_extra_user');

            $table->index('is_extra_user');
            $table->index('parent_tariff_id');
        });
    }

    public function down(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->dropIndex(['is_extra_user']);
            $table->dropIndex(['parent_tariff_id']);
            $table->dropColumn(['is_extra_user', 'parent_tariff_id']);
        });
    }
};

