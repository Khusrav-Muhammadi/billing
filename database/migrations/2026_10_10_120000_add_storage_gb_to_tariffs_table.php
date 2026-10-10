<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Лимит файлового хранилища (Cloudflare R2) в тарифах и услугах.
 *
 * - Для тарифа (is_tariff=1) storage_gb = сколько GB включено.
 * - Для услуги type=add_storage storage_gb = сколько GB даёт одна единица пакета.
 *
 * Итог для организации = storage_gb тарифа + Σ(storage_gb × quantity) по активным пакетам.
 * Итог отправляется в CRM как абсолютное значение (см. SyncStorageQuotaJob).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tariffs', 'storage_gb')) {
            Schema::table('tariffs', function (Blueprint $table) {
                $table->unsignedInteger('storage_gb')->nullable()->after('user_count');
            });
        }

        // Включённый объём базовых тарифов CRM (по таблице из ТЗ).
        $included = [
            1 => 5,   // BASE
            2 => 15,  // STANDART
            3 => 30,  // PREMIUM
            4 => 50,  // VIP
        ];

        foreach ($included as $tariffId => $gb) {
            DB::table('tariffs')
                ->where('id', $tariffId)
                ->whereNull('storage_gb')
                ->update(['storage_gb' => $gb]);
        }

        // Доп. пакеты хранилища. Цены админ задаёт в UI, как у остальных услуг.
        $packages = [
            ['name' => 'Хранилище +10 GB', 'storage_gb' => 10],
            ['name' => 'Хранилище +50 GB', 'storage_gb' => 50],
            ['name' => 'Хранилище +100 GB', 'storage_gb' => 100],
            ['name' => 'Хранилище +500 GB', 'storage_gb' => 500],
            ['name' => 'Хранилище +1 TB', 'storage_gb' => 1024],
        ];

        foreach ($packages as $package) {
            $exists = DB::table('tariffs')
                ->where('type', 'add_storage')
                ->where('storage_gb', $package['storage_gb'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('tariffs')->insert([
                'name' => $package['name'],
                'user_count' => null,
                'project_count' => null,
                'storage_gb' => $package['storage_gb'],
                'is_tariff' => false,
                'is_extra_user' => false,
                'type' => 'add_storage',
                'can_increase' => true,
                'is_external' => false,
                'is_public' => false,
                'is_one_time' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tariffs')->where('type', 'add_storage')->delete();

        if (Schema::hasColumn('tariffs', 'storage_gb')) {
            Schema::table('tariffs', function (Blueprint $table) {
                $table->dropColumn('storage_gb');
            });
        }
    }
};
