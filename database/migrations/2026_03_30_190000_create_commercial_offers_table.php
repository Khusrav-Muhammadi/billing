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
        Schema::create('commercial_offers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tariff_id')->nullable()->constrained('tariffs')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('draft')->index();
            $table->timestamp('saved_at')->nullable();
            $table->timestamp('locked_at')->nullable();

            $table->date('pricing_date')->nullable();
            $table->string('currency', 10)->default('USD');
            $table->string('payable_currency', 10)->nullable();
            $table->string('card_payment_type', 20)->nullable();

            $table->unsignedSmallInteger('period_months')->default(6);
            $table->unsignedInteger('extra_users')->default(0);
            $table->string('selected_tariff_key')->nullable();

            $table->string('client_name')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('client_email')->nullable();
            $table->string('partner_name')->nullable();
            $table->string('partner_phone')->nullable();
            $table->string('partner_email')->nullable();
            $table->string('payer_type', 20)->default('client');
            $table->string('manager_name')->nullable();

            $table->decimal('original_total', 20, 2)->default(0);
            $table->decimal('monthly_total', 20, 2)->default(0);
            $table->decimal('period_total', 20, 2)->default(0);
            $table->decimal('grand_total', 20, 2)->default(0);
            $table->decimal('payable_total', 20, 2)->default(0);
            $table->decimal('conversion_rate', 20, 6)->nullable();

            $table->json('selected_services')->nullable();
            $table->json('allowed_payment_methods')->nullable();
            $table->json('snapshot')->nullable();
            $table->text('payment_link')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commercial_offers');
    }
};

