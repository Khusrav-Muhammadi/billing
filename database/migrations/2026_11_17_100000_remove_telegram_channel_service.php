<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Подключение Telegram-аккаунта закрыто: удаляем доп. канал Telegram (Mini App не трогаем). */
    public function up(): void
    {
        $ids = DB::table('tariffs')
            ->where('type', 'add_insta_channel')
            ->where(function ($q) {
                $q->where('name', 'like', '%telegram%')->orWhere('name', 'like', '%телеграм%');
            })
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('tariff_included_services')->whereIn('service_id', $ids)->delete();
        DB::table('tariff_included_services')->whereIn('tariff_id', $ids)->delete();
        DB::table('tariffs')->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        // необратимо
    }
};
