<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\InvoiceRequest;
use App\Repositories\Payment\Contracts\PaymentRepositoryInterface;
use App\Services\Billing\Enum\PaymentOperationType;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{

    public function __construct(public PaymentService $service) { }

    public function createInvoice(InvoiceRequest $request)
    {
        return $this->service->initiatePayment(
            operationType: PaymentOperationType::from($request->operation_type),
            operationData: $request->all(),
            provider: $request->provider
        );
    }

    public function handleWebhook(string $provider, Request $request, PaymentService $paymentService)
    {
        return $paymentService->handleWebhook(
            providerSlug: $provider,
            data: $request->all()
        );
    }
}
