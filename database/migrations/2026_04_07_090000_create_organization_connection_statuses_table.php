<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organization_connection_statuses')) {
            Schema::drop('organization_connection_statuses');
        }

        Schema::create('organization_connection_statuses', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->enum('status', ['connected', 'disconnected'])->index();
            $table->dateTime('status_date')->index();

            $table->foreignId('commercial_offer_id')
                ->nullable()
                ->constrained('commercial_offers')
                ->nullOnDelete();
            $table->unique('commercial_offer_id');

            $table->foreignId('day_closing_id')
                ->nullable()
                ->constrained('day_closings')
                ->nullOnDelete();

            $table->foreignId('author_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('reason', 255)->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status_date'], 'org_conn_status_org_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_connection_statuses');
    }
};
