<?php

namespace Tests\Unit;

use App\Jobs\ConnectionJob;
use App\Jobs\UpdateTariffJob;
use App\Models\ConnectedClientServices;
use App\Models\Organization;
use App\Support\CrmPackTypes;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * update-tariff должен нести активные пакеты: без них CRM снимает права склада/магазина при смене тарифа.
 */
class CrmPackTypesTest extends TestCase
{
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        DB::beginTransaction();

        $this->organization = Organization::query()->whereHas('client')->firstOrFail();
        ConnectedClientServices::query()->where('client_id', $this->organization->id)->delete();

        $this->service(1, true);   // BASE, тариф без type
        $this->service(20, true);  // good_accounting
        $this->service(20, true);  // повтор того же пакета
        $this->service(14, false); // online-shop, уже отключён
        $this->service(19, true);  // sms-mailing
        $this->service(21, true);  // add_user: тоже уходит, CRM сам отбросит
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        parent::tearDown();
    }

    public function test_active_for_returns_distinct_active_pack_types(): void
    {
        $this->assertSame(
            ['add_user', 'good_accounting', 'sms-mailing'],
            CrmPackTypes::activeFor($this->organization)
        );
    }

    public function test_update_tariff_and_connection_jobs_send_packs(): void
    {
        config(['services.sham.crm_token' => 'test-token']);
        Http::fake(['*' => Http::response(['ok' => true])]);

        (new UpdateTariffJob($this->organization, 1, 'demo-sub'))->handle();
        (new ConnectionJob($this->organization, 1, 'demo-sub'))->handle();

        $requests = Http::recorded()->map(fn (array $pair) => $pair[0]);

        $this->assertCount(2, $requests);
        foreach ($requests as $request) {
            /** @var Request $request */
            $this->assertStringEndsWith('/api/organization/update-tariff', $request->url());
            $this->assertSame(1, $request['tariff_id']);
            $this->assertSame(['add_user', 'good_accounting', 'sms-mailing'], $request['packs'] ?? null);
        }
    }

    private function service(int $tariffId, bool $active): void
    {
        ConnectedClientServices::query()->create([
            'client_id' => $this->organization->id,
            'tariff_id' => $tariffId,
            'quantity' => 1,
            'status' => $active,
            'deactivated_at' => $active ? null : now(),
        ]);
    }
}
