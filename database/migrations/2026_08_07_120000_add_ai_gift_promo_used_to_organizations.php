<?php

use App\Models\Ai\AiSubscription;
use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('ai_gift_promo_used')->default(false)->after('has_implementation');
        });

        // Уже оплачивали ИИ-агента — акция считается использованной.
        if (Schema::hasTable('ai_subscriptions')) {
            $orgIds = AiSubscription::query()
                ->distinct()
                ->pluck('organization_id')
                ->filter()
                ->all();

            if ($orgIds !== []) {
                Organization::query()
                    ->whereIn('id', $orgIds)
                    ->update(['ai_gift_promo_used' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('ai_gift_promo_used');
        });
    }
};
