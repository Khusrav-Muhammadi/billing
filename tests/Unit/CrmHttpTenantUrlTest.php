<?php

namespace Tests\Unit;

use App\Support\CrmHttp;
use Tests\TestCase;

class CrmHttpTenantUrlTest extends TestCase
{
    public function test_tenant_host_gets_new_before_back(): void
    {
        $this->assertSame(
            'https://sham-new-back.shamcrm.com/api/organization',
            CrmHttp::tenantUrl('sham', 'shamcrm.com', '/api/organization')
        );

        $this->assertSame(
            'https://fingroupcrm-new-back.shamcrm.com/api/organization',
            CrmHttp::tenantUrl('fingroupcrm', 'shamcrm.com', 'api/organization')
        );
    }

    public function test_subdomain_that_already_ends_with_new_is_not_doubled(): void
    {
        $this->assertSame(
            'https://sham-new-back.shamcrm.com/api/ai/token-logs',
            CrmHttp::tenantUrl('sham-new', 'shamcrm.com', '/api/ai/token-logs')
        );
    }
}
