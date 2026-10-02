<?php

namespace Tests\Unit;

use App\Models\Ai\AiTariffPlan;
use App\Models\Ai\CommercialOfferAiItem;
use App\Models\CommercialOffer;
use App\Models\CommercialOfferItem;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\Tariff;
use App\Services\Payment\InvoicePaymentItemPresenter;
use Tests\TestCase;

class InvoicePaymentItemPresenterTest extends TestCase
{
    public function test_ai_current_month_is_one_and_plan_uses_period_months(): void
    {
        $plan = new AiTariffPlan(['name' => 'AI Chat START', 'category' => 'chat']);
        $aiItem = new CommercialOfferAiItem([
            'period_months' => 11,
            'demo_days' => 0,
            'unit_price' => 152727.2727,
            'total_price' => 1680000,
            'current_month_amount' => 560000,
            'balance_topup' => 0,
        ]);
        $aiItem->setRelation('plan', $plan);

        $offer = new CommercialOffer(['period_months' => 12]);
        $offer->setRelation('items', collect());
        $offer->setRelation('aiItems', collect([$aiItem]));

        $current = new PaymentItem([
            'service_name' => 'ИИ-Агент чатов «AI Chat START (DeepSeek)» — текущий месяц',
            'price' => 560000,
        ]);
        $planLine = new PaymentItem([
            'service_name' => 'ИИ-Агент чатов «AI Chat START (DeepSeek)» (11 мес)',
            'price' => 1680000,
        ]);

        $payment = new Payment();
        $payment->setRelation('paymentItems', collect([$current, $planLine]));

        (new InvoicePaymentItemPresenter())->enrich($payment, $offer);

        $this->assertSame(1, $current->getAttribute('months'));
        $this->assertSame(11, $planLine->getAttribute('months'));
        $this->assertEqualsWithDelta(560000.0, (float) $current->getAttribute('unit_price'), 0.01);
        $this->assertEqualsWithDelta(152727.2727, (float) $planLine->getAttribute('unit_price'), 0.01);
    }

    public function test_ai_does_not_inherit_crm_offer_period(): void
    {
        $offer = new CommercialOffer(['period_months' => 12]);
        $offer->setRelation('items', collect());
        $offer->setRelation('aiItems', collect());

        $item = new PaymentItem([
            'service_name' => 'ИИ-Агент чатов «AI Chat START» — баланс ИИ',
            'price' => 100000,
        ]);
        $payment = new Payment();
        $payment->setRelation('paymentItems', collect([$item]));

        (new InvoicePaymentItemPresenter())->enrich($payment, $offer);

        $this->assertSame(0, $item->getAttribute('months'));
    }

    public function test_crm_line_keeps_offer_item_months(): void
    {
        $tariff = new Tariff(['name' => 'START']);
        $offerItem = new CommercialOfferItem([
            'quantity' => 2,
            'unit_price' => 100,
            'months' => 6,
            'total_price' => 1200,
        ]);
        $offerItem->setRelation('tariff', $tariff);

        $offer = new CommercialOffer(['period_months' => 6]);
        $offer->setRelation('items', collect([$offerItem]));
        $offer->setRelation('aiItems', collect());

        $item = new PaymentItem([
            'service_name' => 'START',
            'price' => 1200,
        ]);
        $payment = new Payment();
        $payment->setRelation('paymentItems', collect([$item]));

        (new InvoicePaymentItemPresenter())->enrich($payment, $offer);

        $this->assertSame(6, $item->getAttribute('months'));
        $this->assertSame(2, $item->getAttribute('quantity'));
        $this->assertEqualsWithDelta(100.0, (float) $item->getAttribute('unit_price'), 0.01);
    }
}
