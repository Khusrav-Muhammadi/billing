<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $table): void {
            $table->dropColumn('api_key');
        });
    }

    public function down(): void
    {
        Schema::table('ai_models', function (Blueprint $table): void {
            $table->string('api_key', 500)->nullable();
        });
    }
};
