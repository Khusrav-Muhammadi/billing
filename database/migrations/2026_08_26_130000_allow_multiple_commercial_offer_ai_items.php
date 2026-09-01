<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_offer_ai_items')) {
            Schema::table('commercial_offer_ai_items', function (Blueprint $table): void {
                $table->dropForeign(['commercial_offer_id']);
                $table->dropUnique(['commercial_offer_id']);
            });
            Schema::table('commercial_offer_ai_items', function (Blueprint $table): void {
                $table->index('commercial_offer_id');
                $table->foreign('commercial_offer_id')
                    ->references('id')
                    ->on('commercial_offers')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('ai_subscriptions')) {
            Schema::table('ai_subscriptions', function (Blueprint $table): void {
                $table->dropForeign(['commercial_offer_id']);
                $table->dropUnique(['commercial_offer_id']);
            });
            Schema::table('ai_subscriptions', function (Blueprint $table): void {
                $table->index('commercial_offer_id');
                $table->unique(['commercial_offer_id', 'plan_id'], 'ai_subscriptions_offer_plan_unique');
                $table->foreign('commercial_offer_id')
                    ->references('id')
                    ->on('commercial_offers')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_subscriptions')) {
            Schema::table('ai_subscriptions', function (Blueprint $table): void {
                $table->dropForeign(['commercial_offer_id']);
                $table->dropUnique('ai_subscriptions_offer_plan_unique');
                $table->dropIndex(['commercial_offer_id']);
            });
            Schema::table('ai_subscriptions', function (Blueprint $table): void {
                $table->unique('commercial_offer_id');
                $table->foreign('commercial_offer_id')
                    ->references('id')
                    ->on('commercial_offers')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('commercial_offer_ai_items')) {
            Schema::table('commercial_offer_ai_items', function (Blueprint $table): void {
                $table->dropForeign(['commercial_offer_id']);
                $table->dropIndex(['commercial_offer_id']);
            });
            Schema::table('commercial_offer_ai_items', function (Blueprint $table): void {
                $table->unique('commercial_offer_id');
                $table->foreign('commercial_offer_id')
                    ->references('id')
                    ->on('commercial_offers')
                    ->cascadeOnDelete();
            });
        }
    }
};
