<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('name');
            $table->string('email')->index();
            $table->string('phone');
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();

            $table->string('status', 32)->default('queued')->index();
            $table->string('step', 32)->nullable();

            $table->string('sub_domain')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->text('login_url')->nullable();
            $table->timestamp('login_url_expires_at')->nullable();

            $table->string('failure_code', 64)->nullable();
            $table->text('failure_reason')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
