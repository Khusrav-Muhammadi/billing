<?php

namespace Tests\Unit;

use App\Models\Ai\CommercialOfferAiItem;
use Tests\TestCase;

class CommercialOfferAiDemoTest extends TestCase
{
    public function test_demo_amount_is_tariff_divided_by_thirty_times_days(): void
    {
        $this->assertSame(30.0, CommercialOfferAiItem::demoAmount(300, 3));
        $this->assertSame(10.0, CommercialOfferAiItem::demoAmount(100, 3));
        $this->assertSame(0.0, CommercialOfferAiItem::demoAmount(0, 3));
    }

    public function test_demo_is_allowed_only_for_connection_flows(): void
    {
        $this->assertTrue(CommercialOfferAiItem::allowsDemoForRequestType('connection'));
        $this->assertTrue(CommercialOfferAiItem::allowsDemoForRequestType('connection_extra_services'));
        $this->assertFalse(CommercialOfferAiItem::allowsDemoForRequestType('renewal'));
        $this->assertFalse(CommercialOfferAiItem::allowsDemoForRequestType('renewal_no_changes'));
    }
}
