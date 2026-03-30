<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->boolean('can_increase')->default(false)->after('end_date');
            $table->index('can_increase');
        });
    }

    public function down(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->dropIndex(['can_increase']);
            $table->dropColumn('can_increase');
        });
    }
};

