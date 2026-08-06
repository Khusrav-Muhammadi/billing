<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_usage_raw_logs')) {
            return;
        }

        Schema::table('ai_usage_raw_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('ai_usage_raw_logs', 'ai_usage_log_id')) {
                $table->unsignedBigInteger('ai_usage_log_id')->nullable()->after('organization_id')->index();
                $table->foreign('ai_usage_log_id')
                    ->references('id')
                    ->on('ai_usage_logs')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_usage_raw_logs')) {
            return;
        }

        Schema::table('ai_usage_raw_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('ai_usage_raw_logs', 'ai_usage_log_id')) {
                $table->dropForeign(['ai_usage_log_id']);
                $table->dropColumn('ai_usage_log_id');
            }
        });
    }
};
