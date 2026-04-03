<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_offers')) {
            if (!Schema::hasColumn('commercial_offers', 'request_type')) {
                Schema::table('commercial_offers', function (Blueprint $table): void {
                    $table->string('request_type', 64)->default('connection')->after('status')->index();
                });
            }

            if (Schema::hasColumn('commercial_offers', 'snapshot')) {
                DB::table('commercial_offers')
                    ->select('id', 'snapshot')
                    ->orderBy('id')
                    ->chunkById(200, function ($rows): void {
                        foreach ($rows as $row) {
                            $snapshot = $row->snapshot;
                            if (is_string($snapshot)) {
                                $snapshot = json_decode($snapshot, true);
                            }

                            if (!is_array($snapshot)) {
                                continue;
                            }

                            $requestType = trim((string) ($snapshot['request_type'] ?? 'connection'));
                            if (!in_array($requestType, ['connection', 'connection_extra_services', 'renewal', 'renewal_no_changes'], true)) {
                                $requestType = 'connection';
                            }

                            DB::table('commercial_offers')
                                ->where('id', (int) $row->id)
                                ->update(['request_type' => $requestType]);
                        }
                    });
            }

            Schema::table('commercial_offers', function (Blueprint $table): void {
                if (Schema::hasColumn('commercial_offers', 'updated_by')) {
                    try {
                        $table->dropConstrainedForeignId('updated_by');
                    } catch (\Throwable $e) {
                        $table->dropColumn('updated_by');
                    }
                }

                $dropColumns = [];
                foreach (['selected_tariff_key', 'selected_services', 'allowed_payment_methods', 'snapshot'] as $column) {
                    if (Schema::hasColumn('commercial_offers', $column)) {
                        $dropColumns[] = $column;
                    }
                }

                if (!empty($dropColumns)) {
                    $table->dropColumn($dropColumns);
                }
            });
        }

        if (Schema::hasTable('commercial_offer_items')) {
            if (!Schema::hasColumn('commercial_offer_items', 'tariff_id')) {
                Schema::table('commercial_offer_items', function (Blueprint $table): void {
                    $table->unsignedBigInteger('tariff_id')->nullable()->after('commercial_offer_id')->index();
                });
            }

            $offerTariffs = [];
            if (Schema::hasTable('commercial_offers')) {
                DB::table('commercial_offers')
                    ->select('id', 'tariff_id')
                    ->orderBy('id')
                    ->chunkById(500, function ($rows) use (&$offerTariffs): void {
                        foreach ($rows as $row) {
                            $offerTariffs[(int) $row->id] = $row->tariff_id ? (int) $row->tariff_id : null;
                        }
                    });
            }

            DB::table('commercial_offer_items')
                ->select('id', 'service_key', 'commercial_offer_id', 'tariff_id')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($offerTariffs): void {
                    foreach ($rows as $row) {
                        if (!empty($row->tariff_id)) {
                            continue;
                        }

                        $resolvedTariffId = null;
                        $serviceKey = trim((string) ($row->service_key ?? ''));
                        if ($serviceKey !== '' && preg_match('/(?:tariff|service)-(\d+)/', $serviceKey, $matches) === 1) {
                            $resolvedTariffId = (int) $matches[1];
                        }

                        if (!$resolvedTariffId) {
                            $offerId = (int) ($row->commercial_offer_id ?? 0);
                            $resolvedTariffId = $offerTariffs[$offerId] ?? null;
                        }

                        if (!$resolvedTariffId) {
                            continue;
                        }

                        DB::table('commercial_offer_items')
                            ->where('id', (int) $row->id)
                            ->update(['tariff_id' => $resolvedTariffId]);
                    }
                });

            Schema::table('commercial_offer_items', function (Blueprint $table): void {
                try {
                    $table->foreign('tariff_id')->references('id')->on('tariffs')->nullOnDelete();
                } catch (\Throwable $e) {
                    // Constraint already exists (or unsupported) - ignore.
                }

                $dropColumns = [];
                foreach (['service_key', 'service_name', 'billing_type', 'meta'] as $column) {
                    if (Schema::hasColumn('commercial_offer_items', $column)) {
                        $dropColumns[] = $column;
                    }
                }

                if (!empty($dropColumns)) {
                    $table->dropColumn($dropColumns);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('commercial_offers')) {
            Schema::table('commercial_offers', function (Blueprint $table): void {
                if (!Schema::hasColumn('commercial_offers', 'updated_by')) {
                    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('commercial_offers', 'selected_tariff_key')) {
                    $table->string('selected_tariff_key')->nullable();
                }
                if (!Schema::hasColumn('commercial_offers', 'selected_services')) {
                    $table->json('selected_services')->nullable();
                }
                if (!Schema::hasColumn('commercial_offers', 'allowed_payment_methods')) {
                    $table->json('allowed_payment_methods')->nullable();
                }
                if (!Schema::hasColumn('commercial_offers', 'snapshot')) {
                    $table->json('snapshot')->nullable();
                }

                if (Schema::hasColumn('commercial_offers', 'request_type')) {
                    $table->dropColumn('request_type');
                }
            });
        }

        if (Schema::hasTable('commercial_offer_items')) {
            Schema::table('commercial_offer_items', function (Blueprint $table): void {
                if (!Schema::hasColumn('commercial_offer_items', 'service_key')) {
                    $table->string('service_key')->nullable();
                }
                if (!Schema::hasColumn('commercial_offer_items', 'service_name')) {
                    $table->string('service_name')->nullable();
                }
                if (!Schema::hasColumn('commercial_offer_items', 'billing_type')) {
                    $table->string('billing_type', 32)->default('period');
                }
                if (!Schema::hasColumn('commercial_offer_items', 'meta')) {
                    $table->json('meta')->nullable();
                }

                if (Schema::hasColumn('commercial_offer_items', 'tariff_id')) {
                    try {
                        $table->dropForeign(['tariff_id']);
                    } catch (\Throwable $e) {
                        // Ignore if no foreign key.
                    }
                    $table->dropColumn('tariff_id');
                }
            });
        }
    }
};
