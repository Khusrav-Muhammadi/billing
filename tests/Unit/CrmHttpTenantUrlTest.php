<?php

namespace Tests\Unit;

use App\Support\CrmHttp;
use Tests\TestCase;

/**
 * tenantUrl() не должен ничего додумывать про поддомен.
 * Хост тенанта — это ровно clients.sub_domain + `-back`.
 */
class CrmHttpTenantUrlTest extends TestCase
{
    public function test_plain_subdomain_gets_only_back_suffix(): void
    {
        // Новые демо создаются без -new: тенант sham-back, и URL должен быть таким же.
        $this->assertSame(
            'https://sham-back.shamcrm.com/api/organization',
            CrmHttp::tenantUrl('sham', 'shamcrm.com', '/api/organization')
        );

        $this->assertSame(
            'https://fingroupcrm-back.shamcrm.com/api/organization',
            CrmHttp::tenantUrl('fingroupcrm', 'shamcrm.com', 'api/organization')
        );
    }

    public function test_subdomain_with_new_is_kept_as_is(): void
    {
        // Старые клиенты с -new в sub_domain продолжают ходить в свой -new-back.
        $this->assertSame(
            'https://sham-new-back.shamcrm.com/api/ai/token-logs',
            CrmHttp::tenantUrl('sham-new', 'shamcrm.com', '/api/ai/token-logs')
        );
    }

    public function test_empty_path_returns_base_url(): void
    {
        $this->assertSame(
            'https://sham-back.shamcrm.com',
            CrmHttp::tenantUrl('sham', 'shamcrm.com')
        );
    }
}
