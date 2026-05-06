<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'implementation_required')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('implementation_required')
                    ->default(false)
                    ->after('has_implementation');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'implementation_required')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('implementation_required');
            });
        }
    }
};

