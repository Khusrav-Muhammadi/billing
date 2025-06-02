<?php

namespace App\Jobs;

use App\Models\OrganizationPack;
use App\Models\Tariff;
use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class ProcessPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private PaymentProviderInterface $provider,
        private string $providerInvoiceId
    ) {}

    public function handle(PaymentService $paymentService)
    {
        $paymentService->confirmPayment(
            get_class($this->provider),
            $this->providerInvoiceId
        );

    }
}
