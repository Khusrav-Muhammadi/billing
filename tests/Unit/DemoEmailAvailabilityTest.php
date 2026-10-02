<?php

namespace Tests\Unit;

use App\Services\Site\DemoEmailAvailability;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemoEmailAvailabilityTest extends TestCase
{
    private const EMAIL = 'zzprobe-subdomain-taken-20261002@gmail.com';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'demo.crm_check_email_url' => 'https://shamcrm.com/api/check-email',
            'demo.crm_check_subdomain_url' => 'https://shamcrm.com/api/checkDomain',
            'app.public_domains' => ['gmail.com'],
        ]);

        Cache::put('demo:email-dns:gmail.com', true, 60);
    }

    public function test_email_is_rejected_when_crm_tenant_already_exists(): void
    {
        Http::fake([
            'https://shamcrm.com/api/check-email*' => Http::response(false, 200),
            'https://shamcrm.com/api/checkDomain' => Http::response([
                'result' => true,
                'errors' => null,
            ], 200),
        ]);

        $check = $this->availability()->check(self::EMAIL);

        $this->assertFalse($check['available']);
        $this->assertSame(DemoEmailAvailability::REASON_SUBDOMAIN, $check['reason']);
        $this->assertSame(
            'Пользователь с таким поддоменом уже существует. Укажите другой email.',
            $check['message']
        );

        Http::assertSent(function ($request) {
            return $request->url() === 'https://shamcrm.com/api/checkDomain'
                && $request['domain'] === 'zzprobe-subdomain-taken-20261002';
        });
    }

    public function test_email_stays_available_when_crm_subdomain_is_free(): void
    {
        Http::fake([
            'https://shamcrm.com/api/check-email*' => Http::response(false, 200),
            'https://shamcrm.com/api/checkDomain' => Http::response([
                'result' => false,
                'errors' => null,
            ], 200),
        ]);

        $check = $this->availability()->check(self::EMAIL);

        $this->assertTrue($check['available']);
        $this->assertNull($check['reason']);
    }

    /**
     * Карточка клиента в этом тесте не нужна: занятость решает ответ CRM.
     */
    private function availability(): DemoEmailAvailability
    {
        return new class extends DemoEmailAvailability {
            protected function clientExistsByEmail(string $email): bool
            {
                return false;
            }

            protected function clientExistsBySubdomain(string $subdomain): bool
            {
                return false;
            }
        };
    }
}
