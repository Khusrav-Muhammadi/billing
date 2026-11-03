<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payme_balance_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('payme_id', 24)->unique();
            $table->unsignedBigInteger('payme_time')->index();
            $table->unsignedBigInteger('organization_id')->index();
            $table->text('account');
            $table->unsignedBigInteger('amount_tiyin');
            $table->unsignedBigInteger('currency_id');
            $table->integer('state');
            $table->unsignedBigInteger('create_time');
            $table->unsignedBigInteger('perform_time')->default(0);
            $table->unsignedBigInteger('cancel_time')->default(0);
            $table->unsignedTinyInteger('reason')->nullable();
            $table->unsignedBigInteger('income_balance_id')->nullable()->unique();
            $table->unsignedBigInteger('reversal_balance_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('payme_balance_transactions')->exists()) {
            throw new RuntimeException('Payme financial records must be reconciled before rollback.');
        }

        Schema::dropIfExists('payme_balance_transactions');
    }
};
