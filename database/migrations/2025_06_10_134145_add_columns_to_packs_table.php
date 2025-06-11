<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('packs', function (Blueprint $table) {
            $table->text('description')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packs', function (Blueprint $table) {
            //
        });
    }
};
//
//
//Schema::create('service_packages', function (Blueprint $table) {
//    $table->id();
//    $table->string('name', 255); -- Название пакета
//    $table->text('description')->nullable(); -- Описание пакета
//    $table->enum('payment_type', ['one_time', 'subscription', 'combined']); -- хамту стринг
//    $table->boolean('is_active')->default(true); -- Активность пакета
//    $table->timestamps();
//
//});
//
//-- Таблица цен пакетов для разных тарифов и валют
//Schema::create('package_prices', function (Blueprint $table) {
//    $table->id();
//    $table->unsignedBigInteger('service_package_id');
//    $table->unsignedBigInteger('tariff_id');
//    $table->unsignedInteger('currency_id');
//
//    -- Цены (в копейках/центах для точности)
//    $table->unsignedInteger('one_time_price')->nullable(); -- Единовременная цена
//    $table->unsignedInteger('monthly_price')->nullable(); -- Ежемесячная цена
//
//    -- Специальные условия
//    $table->boolean('is_free')->default(false); -- Бесплатный для данного тарифа
//    $table->boolean('is_included')->default(false); -- Включен в тариф по умолчанию
//
//    $table->timestamps();
//
//
//
//    $table->index(['tariff_id', 'currency_id']);
//    $table->index(['service_package_id', 'is_free']);
//});
//
//
//
//-- Таблица подписок клиентов на пакеты
//Schema::create('client_package_subscriptions', function (Blueprint $table) {
//    $table->id();
//    $table->unsignedBigInteger('client_id');
//    $table->unsignedBigInteger('package_prices_id'); -- Ссылка на конкретную цену пакета
//
//
//    -- Цены на момент подключения (для истории)
//    $table->unsignedInteger('one_time_paid')->nullable(); -- Сколько заплачено единовременно
//    $table->unsignedInteger('monthly_price')->nullable(); -- Ежемесячная цена на момент подключения
//
//    -- Даты
//    $table->timestamp('activated_at');
//    $table->timestamp('expires_at')->nullable(); -- Для подписок
//    $table->timestamp('next_billing_at')->nullable(); -- Следующее списание
//    $table->timestamp('cancelled_at')->nullable();
//
//    $table->timestamps();
//
//    $table->index(['client_id', 'status']);
//    $table->index(['package_prices_id', 'status']);
//    $table->index(['expires_at', 'status']);
//    $table->index('next_billing_at');
//});

