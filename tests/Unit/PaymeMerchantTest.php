<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        $table->string('name');
        $table->string('order_number')->unique();
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
        $table->unsignedBigInteger('currency_id');
        $table->string('type');
        $table->string('source')->nullable();
        $table->timestamps();
    });
    (require base_path('database/migrations/2026_11_03_000000_create_payme_balance_transactions_table.php'))->up();

    DB::table('currencies')->insert([['id' => 1, 'symbol_code' => 'UZS'], ['id' => 2, 'symbol_code' => 'USD']]);
    DB::table('countries')->insert([['id' => 1, 'currency_id' => 1], ['id' => 2, 'currency_id' => 2]]);
    DB::table('clients')->insert([['id' => 1, 'country_id' => 1], ['id' => 2, 'country_id' => 2]]);
    DB::table('organizations')->insert([
        ['id' => 42, 'name' => 'Пример', 'order_number' => '000000042', 'client_id' => 1],
        ['id' => 43, 'name' => 'USD клиент', 'order_number' => '000000043', 'client_id' => 2],
    ]);
    DB::table('tariffs')->insert(['id' => 1, 'is_tariff' => true, 'is_extra_user' => false, 'is_external' => false]);
    DB::table('connected_client_services')->insert([
        ['client_id' => 42, 'tariff_id' => 1, 'status' => true, 'date' => now()->subDay(), 'offer_currency_id' => 1],
        ['client_id' => 43, 'tariff_id' => 1, 'status' => true, 'date' => now()->subDay(), 'offer_currency_id' => 2],
    ]);

    config()->set('services.payme_merchant.login', 'Paycom');
    config()->set('services.payme_merchant.key', 'test-key');
    config()->set('services.payme_merchant.merchant_id', 'merchant123');
    config()->set('services.payme_merchant.checkout_url', 'https://test.paycom.uz');
    config()->set('services.sham.crm_token', 'crm-token');
    test()->withHeaders(['Authorization' => 'Basic ' . base64_encode('Paycom:test-key')]);
});

function paymeRpc(string $method, array $params, int $id = 7): array
{
    return ['id' => $id, 'method' => $method, 'params' => $params];
}

function paymeCreate(string $id = 'aaaaaaaaaaaaaaaaaaaaaaaa', ?int $time = null): array
{
    return paymeRpc('CreateTransaction', [
        'id' => $id,
        'time' => $time ?? (int) floor(microtime(true) * 1000),
        'amount' => 100000000,
        'account' => ['order_number' => '000000042'],
    ]);
}

it('requires Payme Basic Auth and preserves the RPC id in errors', function () {
    $request = paymeRpc('CheckPerformTransaction', ['amount' => 100000000, 'account' => ['order_number' => '000000042']]);

    $this->withHeaders(['Authorization' => ''])->postJson('/api/payme/merchant', $request)
        ->assertOk()->assertJsonPath('id', 7)->assertJsonPath('error.code', -32504);
    $this->withHeaders(['Authorization' => 'Basic ' . base64_encode('Paycom:wrong')])
        ->postJson('/api/payme/merchant', $request)->assertJsonPath('error.code', -32504);
    $this->withHeaders(['Authorization' => 'Basic !!!'])
        ->postJson('/api/payme/merchant', $request)->assertJsonPath('id', 7)->assertJsonPath('error.code', -32504);
    $this->withHeaders(['Authorization' => 'Basic ' . base64_encode('Paycom')])
        ->postJson('/api/payme/merchant', $request)->assertJsonPath('error.code', -32504);
    config()->set('services.payme_merchant.login', '');
    config()->set('services.payme_merchant.key', '');
    $this->withHeaders(['Authorization' => 'Basic ' . base64_encode(':')])
        ->postJson('/api/payme/merchant', $request)->assertJsonPath('error.code', -32504);
    expect(DB::table('payme_balance_transactions')->count())->toBe(0);
});

it('accepts test credentials in production and performs without crediting the balance', function () {
    config()->set('services.payme_merchant.test_login', 'TestPaycom');
    config()->set('services.payme_merchant.test_key', 'test:key');
    config()->set('app.env', 'production');
    $request = paymeRpc('CheckPerformTransaction', ['amount' => 100000000, 'account' => ['order_number' => '000000042']]);
    $this->withHeaders(['Authorization' => 'Basic ' . base64_encode('TestPaycom:test:key')])
        ->postJson('/api/payme/merchant', $request)->assertJsonPath('result.allow', true);

    $create = paymeCreate();
    $this->postJson('/api/payme/merchant', $create)->assertJsonPath('result.state', 1);
    $this->postJson('/api/payme/merchant', paymeRpc('PerformTransaction', ['id' => $create['params']['id']]))
        ->assertJsonPath('result.state', 2);
    expect(DB::table('client_balances')->count())->toBe(0);
});

it('checks exact account and fixed tiyin amount before creating a transaction', function () {
    $check = paymeRpc('CheckPerformTransaction', ['amount' => 100000000, 'account' => ['order_number' => '000000042']]);
    $this->postJson('/api/payme/merchant', $check)->assertJsonPath('result.allow', true)->assertJsonPath('id', 7);

    $check['params']['amount'] = 100000001;
    $this->postJson('/api/payme/merchant', $check)->assertJsonPath('error.code', -31001);
    $check['params']['amount'] = 100000000;
    $check['params']['account']['order_number'] = '42';
    $this->postJson('/api/payme/merchant', $check)
        ->assertJsonPath('error.code', -31050)->assertJsonPath('error.data', 'order_number');
    $check['params']['account']['order_number'] = '000000043';
    $this->postJson('/api/payme/merchant', $check)->assertJsonPath('error.code', -31050);
});

it('creates and performs once, then reports the same transaction on retries and in the statement', function () {
    config()->set('app.env', 'production');
    $create = paymeCreate();
    $time = $create['params']['time'];
    $created = $this->postJson('/api/payme/merchant', $create)
        ->assertJsonPath('result.state', 1)->json('result');
    $this->postJson('/api/payme/merchant', $create)->assertJsonPath('result', $created);

    DB::table('connected_client_services')->where('client_id', 42)->update(['status' => false]);
    $perform = paymeRpc('PerformTransaction', ['id' => $create['params']['id']]);
    $paid = $this->postJson('/api/payme/merchant', $perform)
        ->assertJsonPath('result.state', 2)->json('result');
    $this->postJson('/api/payme/merchant', $perform)->assertJsonPath('result', $paid);
    $this->postJson('/api/payme/merchant', paymeRpc('CheckTransaction', ['id' => $create['params']['id']]))
        ->assertJsonPath('result.state', 2)->assertJsonPath('result.perform_time', $paid['perform_time']);

    $statement = paymeRpc('GetStatement', ['from' => $time, 'to' => $time]);
    $this->postJson('/api/payme/merchant', $statement)
        ->assertJsonCount(1, 'result.transactions')
        ->assertJsonPath('result.transactions.0.account.order_number', '000000042')
        ->assertJsonPath('result.transactions.0.amount', 100000000)
        ->assertJsonPath('result.transactions.0.state', 2);
    expect(DB::table('client_balances')->count())->toBe(0)
        ->and(DB::table('payme_balance_transactions')->value('income_balance_id'))->toBeNull();
});

it('allows two pending payments for one cumulative account and sorts the statement by Payme time', function () {
    $time = (int) floor(microtime(true) * 1000);
    $later = paymeCreate('bbbbbbbbbbbbbbbbbbbbbbbb', $time + 1);
    $earlier = paymeCreate('aaaaaaaaaaaaaaaaaaaaaaaa', $time);

    $this->postJson('/api/payme/merchant', $later)->assertJsonPath('result.state', 1);
    $this->postJson('/api/payme/merchant', $earlier)->assertJsonPath('result.state', 1);
    expect(DB::table('payme_balance_transactions')->where('state', 1)->count())->toBe(2);
    $this->postJson('/api/payme/merchant', paymeRpc('PerformTransaction', ['id' => $later['params']['id']]))
        ->assertJsonPath('result.state', 2);
    $this->postJson('/api/payme/merchant', paymeRpc('PerformTransaction', ['id' => $earlier['params']['id']]))
        ->assertJsonPath('result.state', 2);

    $this->postJson('/api/payme/merchant', paymeRpc('GetStatement', ['from' => $time, 'to' => $time + 1]))
        ->assertJsonCount(2, 'result.transactions')
        ->assertJsonPath('result.transactions.0.id', $earlier['params']['id'])
        ->assertJsonPath('result.transactions.1.id', $later['params']['id']);
    expect(DB::table('client_balances')->count())->toBe(0);
});

it('cancels a paid transaction without changing the balance and preserves the original cancellation', function () {
    $create = paymeCreate();
    $this->postJson('/api/payme/merchant', $create)->assertJsonPath('result.state', 1);
    $this->postJson('/api/payme/merchant', paymeRpc('PerformTransaction', ['id' => $create['params']['id']]))
        ->assertJsonPath('result.state', 2);

    $cancel = paymeRpc('CancelTransaction', ['id' => $create['params']['id'], 'reason' => 5]);
    $cancelled = $this->postJson('/api/payme/merchant', $cancel)
        ->assertJsonPath('result.state', -2)->json('result');
    $this->postJson('/api/payme/merchant', $cancel)->assertJsonPath('result', $cancelled);
    $this->postJson('/api/payme/merchant', paymeRpc('PerformTransaction', ['id' => $create['params']['id']]))
        ->assertJsonPath('error.code', -31008);
    expect(DB::table('client_balances')->count())->toBe(0)
        ->and(DB::table('payme_balance_transactions')->value('reversal_balance_id'))->toBeNull()
        ->and(DB::table('payme_balance_transactions')->value('reason'))->toBe(5);
});

it('cancels a pending transaction and rejects a late Perform without a balance entry', function () {
    $create = paymeCreate();
    $this->postJson('/api/payme/merchant', $create)->assertJsonPath('result.state', 1);
    $this->postJson('/api/payme/merchant', paymeRpc('CancelTransaction', ['id' => $create['params']['id'], 'reason' => 4]))
        ->assertJsonPath('result.state', -1);
    $this->postJson('/api/payme/merchant', paymeRpc('PerformTransaction', ['id' => $create['params']['id']]))
        ->assertJsonPath('error.code', -31008);
    expect(DB::table('client_balances')->count())->toBe(0);
});

it('expires a pending transaction after twelve hours and reports it in GetStatement', function () {
    $time = (int) floor(microtime(true) * 1000) - 43200001;
    $create = paymeCreate('bbbbbbbbbbbbbbbbbbbbbbbb', $time);
    $this->postJson('/api/payme/merchant', $create)->assertJsonPath('result.state', 1);

    $this->postJson('/api/payme/merchant', paymeRpc('PerformTransaction', ['id' => $create['params']['id']]))
        ->assertJsonPath('error.code', -31008);
    $this->postJson('/api/payme/merchant', paymeRpc('CheckTransaction', ['id' => $create['params']['id']]))
        ->assertJsonPath('result.state', -1)->assertJsonPath('result.reason', 4);
    $this->postJson('/api/payme/merchant', paymeRpc('GetStatement', ['from' => $time, 'to' => $time]))
        ->assertJsonPath('result.transactions.0.state', -1);
    expect(DB::table('client_balances')->count())->toBe(0);
});

it('performs without requiring the balance table', function () {
    $create = paymeCreate();
    $this->postJson('/api/payme/merchant', $create)->assertJsonPath('result.state', 1);
    Schema::drop('client_balances');

    $this->postJson('/api/payme/merchant', paymeRpc('PerformTransaction', ['id' => $create['params']['id']]))
        ->assertJsonPath('result.state', 2);
    expect(DB::table('payme_balance_transactions')->value('state'))->toBe(2)
        ->and(DB::table('payme_balance_transactions')->value('income_balance_id'))->toBeNull();
});

it('refuses cancellation of a transaction that was already credited before sandbox mode', function () {
    $create = paymeCreate();
    $this->postJson('/api/payme/merchant', $create)->assertJsonPath('result.state', 1);
    DB::table('payme_balance_transactions')->update(['state' => 2, 'income_balance_id' => 123]);

    $this->postJson('/api/payme/merchant', paymeRpc('CancelTransaction', [
        'id' => $create['params']['id'], 'reason' => 5,
    ]))->assertJsonPath('error.code', -31008);
    expect(DB::table('payme_balance_transactions')->value('state'))->toBe(2)
        ->and(DB::table('client_balances')->count())->toBe(0);
});

it('logs the Payme request and response without Basic credentials', function () {
    Log::spy();
    $request = paymeRpc('CheckPerformTransaction', [
        'amount' => 100000000,
        'account' => ['order_number' => '000000042'],
    ]);

    $this->postJson('/api/payme/merchant', $request)->assertJsonPath('result.allow', true);

    Log::shouldHaveReceived('info')->once()->with('Payme merchant callback', Mockery::on(
        fn (array $context): bool => $context['method'] === 'CheckPerformTransaction'
            && $context['rpc_id'] === 7
            && $context['params'] === $request['params']
            && $context['response']['result']['allow'] === true
            && !str_contains(json_encode($context), 'test-key')
    ));
});
