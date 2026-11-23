<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Индекс здоровья: пункт «Сделки» стал «Сущности» — лиды, сделки, заказы, товары и смены этапа.
 * Чистый SQL: MySQL 8 умеет RENAME COLUMN, doctrine/dbal не нужен.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('client_health_snapshots', 'deals_score')) {
            DB::statement('ALTER TABLE client_health_snapshots RENAME COLUMN deals_score TO entities_score');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('client_health_snapshots', 'entities_score')) {
            DB::statement('ALTER TABLE client_health_snapshots RENAME COLUMN entities_score TO deals_score');
        }
    }
};
