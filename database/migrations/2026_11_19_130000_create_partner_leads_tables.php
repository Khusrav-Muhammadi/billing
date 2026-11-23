<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Тёплые лиды: заявки на демо с сайта раздаются партнёрам по очереди.
 * У партнёра 24 часа на реакцию в своей CRM, иначе лид уходит следующему.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('demo_request_id')->unique();
            $table->string('name');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            // queued -> assigned -> accepted | unassigned (никто не взял — менеджерам shamCRM)
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedBigInteger('partner_id')->nullable()->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_lead_id')->constrained('partner_leads')->cascadeOnDelete();
            $table->unsignedBigInteger('partner_id')->index();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('crm_lead_id')->nullable();
            $table->boolean('crm_created')->default(true);
            // active -> accepted | expired | failed
            $table->string('outcome', 20)->default('active')->index();
            $table->timestamp('assigned_at');
            $table->timestamp('deadline_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->json('activity')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });

        // Уже одобренные партнёры с привязанным демо: демо становится их рабочей CRM (NFR) и принимает лиды.
        DB::table('clients')
            ->whereIn('id', DB::table('users')
                ->where('role', 'partner')
                ->where('approval_status', 'approved')
                ->whereNotNull('demo_client_id')
                ->select('demo_client_id'))
            ->update(['nfr' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_lead_assignments');
        Schema::dropIfExists('partner_leads');
    }
};
