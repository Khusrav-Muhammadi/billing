<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('prices', 'organization_id')) {
            Schema::table('prices', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('tariff_id');
                $table->index('organization_id');
            });
        }

        // Backfill: legacy per-client prices -> per-organization prices.
        // If a client has multiple organizations, replicate the row for each org.
        if (Schema::hasColumn('prices', 'client_id')) {
            $orgsByClient = DB::table('organizations')
                ->select(['id', 'client_id'])
                ->whereNotNull('client_id')
                ->get()
                ->groupBy('client_id')
                ->map(fn($rows) => $rows->pluck('id')->values()->all())
                ->all();

            $prices = DB::table('prices')
                ->select(['id', 'client_id', 'tariff_id', 'start_date', 'date', 'sum', 'currency_id', 'kind', 'created_at', 'updated_at'])
                ->whereNotNull('client_id')
                ->orderBy('id')
                ->get();

            foreach ($prices as $row) {
                $orgIds = $orgsByClient[$row->client_id] ?? [];
                if (!$orgIds) {
                    // No organizations for this client: keep it as "общая цена" (organization_id null)
                    continue;
                }

                $firstOrgId = array_shift($orgIds);

                DB::table('prices')
                    ->where('id', $row->id)
                    ->update(['organization_id' => $firstOrgId]);

                foreach ($orgIds as $orgId) {
                    DB::table('prices')->insert([
                        'tariff_id' => $row->tariff_id,
                        'organization_id' => $orgId,
                        'start_date' => $row->start_date,
                        'date' => $row->date,
                        'sum' => $row->sum,
                        'currency_id' => $row->currency_id,
                        'kind' => $row->kind,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }
            }

            Schema::table('prices', function (Blueprint $table) {
                $table->dropColumn('client_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            $table->unsignedInteger('client_id')->nullable()->after('tariff_id');
        });

        Schema::table('prices', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
