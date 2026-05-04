<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('implementation_currency_registries', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('date')->nullable()->index();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('commercial_offer_id')->unique();
            $table->unsignedBigInteger('partner_id')->nullable()->index();
            $table->unsignedBigInteger('offer_currency_id')->nullable()->index();
            $table->unsignedBigInteger('payable_currency_id')->nullable()->index();
            $table->decimal('base_amount', 20, 4)->default(0);
            $table->decimal('discount_percent', 8, 4)->default(0);
            $table->decimal('discount_amount', 20, 4)->default(0);
            $table->decimal('extra_amount', 20, 4)->default(0);
            $table->decimal('total_amount', 20, 4)->default(0);
            $table->decimal('payable_amount', 20, 4)->default(0);
            $table->decimal('conversion_rate', 20, 6)->nullable();
            $table->string('request_type', 64)->nullable()->index();
            $table->timestamps();

            $table->index(['organization_id', 'date'], 'impl_currency_reg_org_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('implementation_currency_registries');
    }
};
