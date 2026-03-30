<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->unsignedInteger('user_count')->nullable()->change();
            $table->unsignedInteger('project_count')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->unsignedInteger('user_count')->nullable(false)->change();
            $table->unsignedInteger('project_count')->nullable(false)->change();
        });
    }
};

