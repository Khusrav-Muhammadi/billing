<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_action_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('commercial_offer_id')->nullable()->constrained('commercial_offers')->nullOnDelete();
            $table->string('type', 20)->index();
            $table->string('action', 100)->nullable()->index();
            $table->string('method', 10)->nullable();
            $table->text('url')->nullable();
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('successful')->nullable()->index();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['organization_id', 'occurred_at'], 'integration_logs_org_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_action_logs');
    }
};
