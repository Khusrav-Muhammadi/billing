<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Enums\PaymentType;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    DB::purge('sqlite');

    Schema::create('currencies', function (Blueprint $table) {
        $table->id();
        $table->string('symbol_code');
    });
    Schema::create('countries', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('currency_id');
    });
    Schema::create('clients', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('country_id');
    });
    Schema::create('organizations', function (Blueprint $table) {
        $table->id();
        $table->string('order_number', 9)->nullable();
        $table->unsignedBigInteger('client_id');
    });
    Schema::create('tariffs', function (Blueprint $table) {
        $table->id();
        $table->boolean('is_tariff');
        $table->boolean('is_extra_user');
        $table->boolean('is_external');
    });
    Schema::create('connected_client_services', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('client_id');
        $table->unsignedBigInteger('tariff_id');
        $table->boolean('status');
        $table->dateTime('date');
        $table->dateTime('deactivated_at')->nullable();
        $table->unsignedBigInteger('offer_currency_id')->nullable();
        $table->unsignedBigInteger('payable_currency_id')->nullable();
    });
    Schema::create('client_balances', function (Blueprint $table) {
        $table->id();
        $table->dateTime('date')->nullable();
        $table->unsignedBigInteger('organization_id');
        $table->decimal('sum', 20, 4);
        $table->unsignedBigInteger('currency_id')->nullable();
        $table->string('type', 16);
        $table->timestamps();
    });

    $migration = require base_path('database/migrations/2026_10_17_000000_create_click_balance_payments_table.php');
    $migration->up();

    DB::table('currencies')->insert([
        ['id' => 1, 'symbol_code' => 'UZS'],
        ['id' => 2, 'symbol_code' => 'USD'],
    ]);
    DB::table('countries')->insert([
        ['id' => 1, 'currency_id' => 1],
        ['id' => 2, 'currency_id' => 2],
    ]);
    DB::table('clients')->insert([
        ['id' => 1, 'country_id' => 1],
        ['id' => 2, 'country_id' => 2],
    ]);
    DB::table('organizations')->insert([
        ['id' => 42, 'order_number' => '000000042', 'client_id' => 1],
        ['id' => 43, 'order_number' => null, 'client_id' => 1],
        ['id' => 44, 'order_number' => '000000044', 'client_id' => 2],
    ]);
    DB::table('tariffs')->insert(['id' => 1, 'is_tariff' => true, 'is_extra_user' => false, 'is_external' => false]);
    DB::table('connected_client_services')->insert([
        ['client_id' => 42, 'tariff_id' => 1, 'status' => true, 'date' => now()->subDay(), 'offer_currency_id' => 1],
        ['client_id' => 43, 'tariff_id' => 1, 'status' => true, 'date' => now()->subDay(), 'offer_currency_id' => 1],
        ['client_id' => 44, 'tariff_id' => 1, 'status' => true, 'date' => now()->subDay(), 'offer_currency_id' => 2],
    ]);

    config()->set('services.click_advanced.service_id', '123');
    config()->set('services.click_advanced.secret_key', 'test-click-secret');
    config()->set('services.sham.crm_token', 'test-billing-crm-token');
    test()->withHeaders(['Authorization' => 'Basic ' . base64_encode('123:test-billing-crm-token')]);
});

function clickAdvancedPayload(int $action, int $paydocId, int $attemptId, array $params, ?string $prepareId = null, ?int $error = null): array
{
    $signTime = '2026-10-17 10:00:00';
    $payload = [
        'action' => $action,
        'click_paydoc_id' => $paydocId,
        'attempt_trans_id' => $attemptId,
        'service_id' => 123,
        'params' => $params,
        'sign_time' => $signTime,
    ];
    if ($prepareId !== null) {
        $payload['merchant_prepare_id'] = $prepareId;
    }
    if ($error !== null) {
        $payload['error'] = $error;
    }
    $payload['sign_string'] = md5($paydocId . $attemptId . '123test-click-secret'
        . implode('', array_map('strval', array_values($params))) . $action . $signTime);

    return $payload;
}

it('requires Basic Auth with the CLICK service ID and billing CRM token', function () {
    $payload = ['service_id' => 123, 'action' => 0, 'params' => ['account' => '42']];

    $this->withHeaders(['Authorization' => ''])->postJson('/api/click/advanced-shop', $payload)
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Basic realm="CLICK Advanced Shop"');
    $this->withHeaders(['Authorization' => 'Basic ' . base64_encode('123:wrong-token')])
        ->postJson('/api/click/advanced-shop', $payload)
        ->assertUnauthorized();
    $this->withHeaders(['Authorization' => 'Basic ' . base64_encode('999:test-billing-crm-token')])
        ->postJson('/api/click/advanced-shop', $payload)
        ->assertUnauthorized();
    $this->withToken('test-billing-crm-token')->postJson('/api/click/advanced-shop', $payload)
        ->assertUnauthorized();

    config()->set('services.sham.crm_token', '');
    $this->withHeaders(['Authorization' => 'Basic ' . base64_encode('123:test-billing-crm-token')])
        ->postJson('/api/click/advanced-shop', $payload)
        ->assertServiceUnavailable();
});

it('returns only the canonical account for a UZS organization with a tariff', function () {
    $this->postJson('/api/click/advanced-shop', [
        'service_id' => 123, 'action' => 0, 'params' => ['account' => '43'],
    ])->assertOk()->assertJsonPath('params.account', '000000043');

    foreach (['99', '44'] as $account) {
        $this->postJson('/api/click/advanced-shop', [
            'service_id' => 123, 'action' => 0, 'params' => ['account' => $account],
        ])->assertOk()->assertJsonPath('error', -5);
    }

    $this->postJson('/api/click/advanced-shop', [
        'service_id' => 999, 'action' => 0, 'params' => ['account' => '42'],
    ])->assertOk()->assertJsonPath('error', -8);
});

it('validates Prepare and Confirm callbacks without creating payments or balance entries', function () {
    $params = ['amount' => '1000.50', 'account' => '000000042'];

    $prepare = $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(1, 100, 1, $params))
        ->assertOk()->assertJsonPath('error', -7)->json();
    expect($prepare['error_note'])->toContain('Данные проверены');

    $confirm = $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(2, 100, 1, $params, '1', 0))
        ->assertOk()->assertJsonPath('error', -7)->json();
    expect($confirm['error_note'])->toContain('Данные проверены');

    $badSignature = clickAdvancedPayload(1, 101, 1, $params);
    $badSignature['sign_string'] = str_repeat('0', 32);
    $this->postJson('/api/click/advanced-shop', $badSignature)->assertJsonPath('error', -1);
    $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(1, 102, 1,
        ['amount' => '1000.50', 'account' => '000000044']))->assertJsonPath('error', -5);
    $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(1, 103, 1,
        ['amount' => 0, 'account' => '000000042']))->assertJsonPath('error', -2);
    $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(2, 104, 1, $params))
        ->assertJsonPath('error', -8);

    expect(DB::table('click_balance_payments')->count())->toBe(0)
        ->and(DB::table('client_balances')->count())->toBe(0);
});

it('credits once for repeated Confirm and rejects another attempt for the same paydoc', function () {
    $this->markTestSkipped('Temporary callback validation mode: payment activation is disabled.');

    $params = ['amount' => '1000.50', 'account' => '000000042'];
    $prepare = $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(1, 100, 1, $params))
        ->assertOk()->assertJsonPath('error', 0)->json();

    $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(1, 100, 1, $params))
        ->assertJsonPath('merchant_prepare_id', $prepare['merchant_prepare_id']);

    $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(1, 100, 3,
        ['amount' => '1001.50', 'account' => '000000042']))->assertJsonPath('error', -2);
    $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(1, 100, 3,
        ['amount' => '1000.50', 'account' => '000000043']))->assertJsonPath('error', -5);

    $second = $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(1, 100, 2, $params))
        ->assertJsonPath('error', 0)->json();

    $confirm = clickAdvancedPayload(2, 100, 1, $params, $prepare['merchant_prepare_id']);
    $this->postJson('/api/click/advanced-shop', $confirm)
        ->assertOk()->assertJsonPath('error', 0)
        ->assertJsonPath('merchant_confirm_id', $prepare['merchant_prepare_id']);
    $this->postJson('/api/click/advanced-shop', $confirm)->assertJsonPath('error', 0);
    $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(1, 100, 1, $params))
        ->assertJsonPath('merchant_prepare_id', $prepare['merchant_prepare_id']);
    $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(2, 100, 2, $params, $second['merchant_prepare_id']))
        ->assertJsonPath('error', -4);
    $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(1, 100, 2, $params))
        ->assertJsonPath('error', -4);
    $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(1, 100, 3, $params))
        ->assertJsonPath('error', -4);

    expect(DB::table('client_balances')->where('type', 'income')->count())->toBe(1)
        ->and(DB::table('client_balances')->value('sum'))->toBe(1000.5)
        ->and(DB::table('click_balance_payments')->where('status', 'paid')->count())->toBe(1);
});

it('rejects bad signatures, changed amounts, and cancelled payments', function () {
    $this->markTestSkipped('Temporary callback validation mode: payment activation is disabled.');

    $params = ['account' => '42', 'amount' => '25.00'];
    $bad = clickAdvancedPayload(1, 101, 1, $params);
    $bad['sign_string'] = str_repeat('0', 32);
    $this->postJson('/api/click/advanced-shop', $bad)->assertJsonPath('error', -1);

    $prepare = $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(1, 101, 1, $params))
        ->assertJsonPath('error', 0)->json();
    $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(2, 101, 1,
        ['account' => '42', 'amount' => '26.00'], $prepare['merchant_prepare_id']))
        ->assertJsonPath('error', -2);
    $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(2, 101, 1,
        $params, '999'))->assertJsonPath('error', -6);

    $cancel = clickAdvancedPayload(2, 101, 1, $params, $prepare['merchant_prepare_id'], -1);
    $this->postJson('/api/click/advanced-shop', $cancel)->assertJsonPath('error', -9);
    $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(2, 101, 1, $params, $prepare['merchant_prepare_id']))
        ->assertJsonPath('error', -9);
    expect(DB::table('client_balances')->count())->toBe(0);
});

it('reverses a paid transaction only once and keeps both ledger entries attributed to CLICK', function () {
    $this->markTestSkipped('Temporary callback validation mode: payment activation is disabled.');

    $params = ['account' => '42', 'amount' => 500];
    $prepare = $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(1, 102, 1, $params))
        ->assertJsonPath('error', 0)->json();
    $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(2, 102, 1, $params, $prepare['merchant_prepare_id']))
        ->assertJsonPath('error', 0);

    $cancel = clickAdvancedPayload(2, 102, 1, $params, $prepare['merchant_prepare_id'], -1);
    $this->postJson('/api/click/advanced-shop', $cancel)->assertJsonPath('error', -9);
    $this->postJson('/api/click/advanced-shop', $cancel)->assertJsonPath('error', -9);

    expect(DB::table('client_balances')->where('source', PaymentType::CLICK->value)->count())->toBe(2)
        ->and(DB::table('client_balances')->where('type', 'outcome')->count())->toBe(1)
        ->and(DB::table('click_balance_payments')->value('status'))->toBe('cancelled');
});

it('keeps the payment prepared if writing to the balance ledger fails', function () {
    $this->markTestSkipped('Temporary callback validation mode: payment activation is disabled.');

    $params = ['account' => '42', 'amount' => 100];
    $prepare = $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(1, 103, 1, $params))
        ->assertJsonPath('error', 0)->json();

    Schema::drop('client_balances');
    $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(2, 103, 1, $params, $prepare['merchant_prepare_id']))
        ->assertJsonPath('error', -7);

    expect(DB::table('click_balance_payments')->value('status'))->toBe('prepared')
        ->and(DB::table('click_balance_payments')->value('paid_paydoc_id'))->toBeNull();
});

it('rejects payment actions without a CLICK secret key', function () {
    config()->set('services.click_advanced.secret_key', '');
    $this->postJson('/api/click/advanced-shop', clickAdvancedPayload(1, 104, 1,
        ['account' => '42', 'amount' => 10]))->assertJsonPath('error', -7);

    expect(DB::table('click_balance_payments')->count())->toBe(0);
});

it('does not claim to support Check or Compare before those methods are implemented', function () {
    foreach ([3, 4] as $action) {
        $this->postJson('/api/click/advanced-shop', [
            'service_id' => 123, 'action' => $action,
        ])->assertOk()->assertJsonPath('error', -3);
    }
});
