<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Индекс здоровья клиента (светофор): одна строка на клиента (тенант CRM) и отчётный день.
 * Считает команда app:client-health-calculate по сырым цифрам из CRM (GET /api/billing/health-metrics).
 * Историю не перезаписываем задним числом без нужды: повторный расчёт того же дня обновляет строку.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_health_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->date('date');
            // paid — платный (есть подключение), demo — действующее демо.
            $table->string('client_type', 10);

            // Баллы 0–100. null — пункт не считается (клиент не ведёт диалоги или сделки), его вес ушёл остальным.
            $table->decimal('logins_score', 5, 1)->nullable();
            $table->decimal('coverage_score', 5, 1)->nullable();
            $table->decimal('dialogs_score', 5, 1)->nullable();
            $table->decimal('deals_score', 5, 1)->nullable();
            $table->unsignedTinyInteger('health_index');

            // green, yellow, red, grey
            $table->string('color', 10);
            // [{code, text}] — все сработавшие правила; reason_text — для списка.
            $table->json('reasons')->nullable();
            $table->string('reason_text', 500)->nullable();

            $table->date('paid_until')->nullable();
            $table->date('period_started_at')->nullable();
            // Сырые цифры из CRM (пользователи, активность, недели) — чтобы разобрать спорный цвет.
            $table->json('metrics')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'date']);
            $table->index(['date', 'color']);
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_health_snapshots');
    }
};
