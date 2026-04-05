<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GeoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now();

            $this->upsertById('currencies', 1, [
                'name' => 'Доллар США',
                'symbol_code' => 'USD',
                'updated_at' => $now,
            ], $now);

            $this->upsertById('currencies', 2, [
                'name' => 'Узбекский сум',
                'symbol_code' => 'UZS',
                'updated_at' => $now,
            ], $now);

            $this->upsertById('currencies', 3, [
                'name' => 'Таджикский сомони',
                'symbol_code' => 'TJS',
                'updated_at' => $now,
            ], $now);

            $this->upsertById('countries', 1, [
                'name' => 'Таджикистан',
                'currency_id' => 3,
                'updated_at' => $now,
            ], $now);

            $this->upsertById('countries', 2, [
                'name' => 'Узбекистан',
                'currency_id' => 2,
                'updated_at' => $now,
            ], $now);

            $this->upsertById('countries', 3, [
                'name' => 'США',
                'currency_id' => 1,
                'updated_at' => $now,
            ], $now);

            $this->normalizeClientCountriesByPhone();
        });
    }

    private function upsertById(string $table, int $id, array $values, $now): void
    {
        $exists = DB::table($table)->where('id', $id)->exists();
        if ($exists) {
            DB::table($table)->where('id', $id)->update($values);
            return;
        }

        DB::table($table)->insert(array_merge(['id' => $id], $values, [
            'created_at' => $now,
        ]));
    }

    private function normalizeClientCountriesByPhone(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('clients')) {
            return;
        }

        $clients = DB::table('clients')->select(['id', 'phone', 'country_id'])->orderBy('id')->get();
        foreach ($clients as $client) {
            $phone = trim((string) ($client->phone ?? ''));
            if ($phone === '') {
                continue;
            }

            $targetCountryId = null;
            if (str_starts_with($phone, '+998') || str_starts_with(preg_replace('/\\D+/', '', $phone), '998')) {
                $targetCountryId = 2;
            } elseif (str_starts_with($phone, '+992') || str_starts_with(preg_replace('/\\D+/', '', $phone), '992')) {
                $targetCountryId = 1;
            } elseif (str_starts_with($phone, '+1')) {
                $targetCountryId = 3;
            }

            if (!$targetCountryId) {
                continue;
            }

            if ((int) $client->country_id === $targetCountryId) {
                continue;
            }

            DB::table('clients')->where('id', (int) $client->id)->update([
                'country_id' => $targetCountryId,
                'updated_at' => now(),
            ]);
        }
    }
}

