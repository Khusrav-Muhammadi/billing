<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Enums\PaymentType;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_balances', function (Blueprint $table): void {
            $table->string('source', 32)->nullable()->after('type');
        });

        Schema::create('click_balance_payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('click_paydoc_id');
            $table->unsignedBigInteger('attempt_trans_id');
            $table->unsignedBigInteger('paid_paydoc_id')->nullable()->unique();
            $table->unsignedBigInteger('organization_id')->index();
            $table->string('account', 9);
            $table->decimal('amount', 20, 2);
            $table->unsignedBigInteger('currency_id');
            $table->string('status', 16);
            $table->unsignedBigInteger('income_balance_id')->nullable()->unique();
            $table->unsignedBigInteger('reversal_balance_id')->nullable()->unique();
            $table->integer('provider_error')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['click_paydoc_id', 'attempt_trans_id'], 'click_balance_attempt_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('click_balance_payments')->exists()
            || DB::table('client_balances')->where('source', PaymentType::CLICK->value)->exists()) {
            throw new RuntimeException('CLICK financial records must be reconciled before rollback.');
        }

        Schema::dropIfExists('click_balance_payments');
        Schema::table('client_balances', function (Blueprint $table): void {
            $table->dropColumn('source');
        });
    }
};
