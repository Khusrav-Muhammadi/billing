<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('organizations') || Schema::hasColumn('organizations', 'partner_auto_cleared_at')) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table): void {
            $table->timestamp('partner_auto_cleared_at')->nullable()->after('client_id')->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('organizations') || !Schema::hasColumn('organizations', 'partner_auto_cleared_at')) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('partner_auto_cleared_at');
        });
    }
};
