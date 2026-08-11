<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_offer_ai_items', function (Blueprint $table): void {
            $table->unsignedTinyInteger('gift_months')->default(0)->after('period_months');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_offer_ai_items', function (Blueprint $table): void {
            $table->dropColumn('gift_months');
        });
    }
};
