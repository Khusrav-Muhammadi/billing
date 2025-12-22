<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientPaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\Billing\BillingService;
use App\Services\Billing\Enum\PaymentOperationType;
use App\Services\Sale\SaleService;

class ClientPaymentController extends Controller
{

    public function index()
    {
        $clients = Payment::query()
            ->with('paymentItems')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('payments', compact('clients'));
    }

    public function store(ClientPaymentRequest $request)
    {
        $data = $request->validated();

        Payment::create([
            'name' => $data['name']
        ]);
    }

}
