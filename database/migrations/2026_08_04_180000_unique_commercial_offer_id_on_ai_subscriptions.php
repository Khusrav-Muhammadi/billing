<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );

        return count($result) > 0;
    }

    public function up(): void
    {
        if (! Schema::hasTable('ai_subscriptions')) {
            return;
        }

        // Keep oldest row per commercial_offer_id, drop the rest.
        $duplicateOfferIds = DB::table('ai_subscriptions')
            ->select('commercial_offer_id')
            ->whereNotNull('commercial_offer_id')
            ->groupBy('commercial_offer_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('commercial_offer_id');

        foreach ($duplicateOfferIds as $offerId) {
            $ids = DB::table('ai_subscriptions')
                ->where('commercial_offer_id', $offerId)
                ->orderBy('id')
                ->pluck('id');

            $ids->shift();
            if ($ids->isEmpty()) {
                continue;
            }

            DB::table('ai_subscriptions')
                ->whereIn('id', $ids->all())
                ->delete();
        }

        if ($this->indexExists('ai_subscriptions', 'ai_subscriptions_commercial_offer_id_index')) {
            Schema::table('ai_subscriptions', function (Blueprint $table): void {
                $table->dropIndex('ai_subscriptions_commercial_offer_id_index');
            });
        }

        if (! $this->indexExists('ai_subscriptions', 'ai_subscriptions_commercial_offer_id_unique')) {
            Schema::table('ai_subscriptions', function (Blueprint $table): void {
                $table->unique('commercial_offer_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_subscriptions')) {
            return;
        }

        if ($this->indexExists('ai_subscriptions', 'ai_subscriptions_commercial_offer_id_unique')) {
            Schema::table('ai_subscriptions', function (Blueprint $table): void {
                $table->dropUnique(['commercial_offer_id']);
            });
        }

        if (! $this->indexExists('ai_subscriptions', 'ai_subscriptions_commercial_offer_id_index')) {
            Schema::table('ai_subscriptions', function (Blueprint $table): void {
                $table->index('commercial_offer_id');
            });
        }
    }
};
