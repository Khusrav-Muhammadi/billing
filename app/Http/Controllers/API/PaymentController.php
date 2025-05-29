<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\InvoiceRequest;
use App\Jobs\ProcessPaymentJob;
use App\Repositories\Payment\Contracts\PaymentRepositoryInterface;
use App\Services\Billing\Enum\PaymentOperationType;
use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\Factory\PaymentProviderFactory;
use App\Services\Payment\PaymentService;
use Dflydev\DotAccessData\Data;
use Illuminate\Http\Request;

class PaymentController extends Controller
{

    public function __construct(public PaymentService $service, public PaymentProviderFactory $providerFactory) { }

    public function createInvoice(InvoiceRequest $request)
    {
        return response()->json($this->service->initiatePayment(
            operationType: PaymentOperationType::from($request->operation_type),
            operationData: $request->all(),
            provider: $request->provider
        ));
    }

    public function handleWebhook(string $provider, Request $request)
    {
        $provider = $this->providerFactory->create($provider);
        $response = $provider->handleWebhook($request->all());

        if ($response->success) {
            $this->service->confirmPayment($response->operationType, $response->operationId);

        }

        return response()->json($response->toArray());
    }

}
