<?php

namespace Tests\Unit;

use App\Services\Site\DemoSubdomainGenerator;
use Tests\TestCase;

class DemoSubdomainGeneratorTest extends TestCase
{
    public function test_demo_subdomain_has_no_new_suffix(): void
    {
        $generator = new DemoSubdomainGenerator();

        $this->assertSame('ivan', $generator->generate('ivan@gmail.com'));
        $this->assertStringEndsNotWith('-new', $generator->generate('sales@acme.tj'));
    }
}
