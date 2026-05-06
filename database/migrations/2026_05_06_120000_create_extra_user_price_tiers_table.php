<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('extra_user_price_tiers')) {
            Schema::create('extra_user_price_tiers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tariff_id');
                $table->unsignedBigInteger('organization_id')->nullable();
                $table->unsignedBigInteger('currency_id');
                $table->unsignedInteger('min_total_users');
                $table->unsignedInteger('max_total_users')->nullable();
                $table->decimal('unit_price', 20, 4);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('extra_user_price_tiers', function (Blueprint $table) {
            if (!$this->indexExists('extra_user_price_tiers', 'eupt_tariff_org_currency_idx')) {
                $table->index(['tariff_id', 'organization_id', 'currency_id'], 'eupt_tariff_org_currency_idx');
            }

            if (!$this->indexExists('extra_user_price_tiers', 'eupt_dates_idx')) {
                $table->index(['start_date', 'end_date'], 'eupt_dates_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_user_price_tiers');
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
