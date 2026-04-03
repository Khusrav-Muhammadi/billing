<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('commercial_offers')) {
            return;
        }

        if (!Schema::hasColumn('commercial_offers', 'status_date')) {
            Schema::table('commercial_offers', function (Blueprint $table): void {
                $table->date('status_date')->nullable()->after('locked_at')->index();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('commercial_offers') || !Schema::hasColumn('commercial_offers', 'status_date')) {
            return;
        }

        Schema::table('commercial_offers', function (Blueprint $table): void {
            $table->dropColumn('status_date');
        });
    }
};
