<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_payment_registries', function (Blueprint $table) {
            $table->id();
            $table->date('offer_date')->nullable()->index();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('commercial_offer_id')->index();
            $table->unsignedBigInteger('partner_id')->nullable()->index();
            $table->string('payment_method', 32)->nullable()->index();
            $table->unsignedBigInteger('account_id')->nullable()->index();

            $table->decimal('gross_amount', 20, 2)->default(0);
            $table->decimal('net_amount', 20, 2)->default(0);
            $table->string('tariff_currency', 10)->nullable();
            $table->decimal('tariff_amount', 20, 2)->default(0);
            $table->string('payment_currency', 10)->nullable();
            $table->decimal('payment_amount', 20, 2)->default(0);
            $table->string('request_type', 64)->nullable()->index();

            $table->timestamps();

            $table->unique('commercial_offer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_payment_registries');
    }
};

