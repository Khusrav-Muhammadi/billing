<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

    }

    public function down(): void
    {
        if (!Schema::hasTable('implementation_discount_caps')) {
            return;
        }

        // In down migration we only remove the unique constraint added in up().
        try {
            Schema::table('implementation_discount_caps', function (Blueprint $table) {
                $table->dropUnique('impl_discount_caps_period_unique');
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
