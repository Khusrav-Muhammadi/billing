<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_offer_ai_items', function (Blueprint $table): void {
            $table->unsignedTinyInteger('demo_days')->default(0)->after('period_months');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_offer_ai_items', function (Blueprint $table): void {
            $table->dropColumn('demo_days');
        });
    }
};
