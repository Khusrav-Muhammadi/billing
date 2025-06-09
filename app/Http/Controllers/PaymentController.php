<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\Billing\BillingService;
use App\Services\Billing\Enum\PaymentOperationType;
use App\Services\Sale\SaleService;

class PaymentController extends Controller
{

    public function __construct(public ClientRepositoryInterface $repository) { }

    public function index()
    {
        $invoices = Invoice::query()
            ->with('invoiceItems')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.payments.index', compact('invoices'));
    }

    public function approveInvoice(Invoice $invoice)
    {
        $operationData = [
            'organization_id' => $invoice->organization_id,
            'invoice_id' => $invoice->id,
            'months' => $invoice->months
        ];

        $service = new BillingService(new SaleService());
        $service->executeOperation(PaymentOperationType::from($invoice->operation_type), $invoice->id);

        return redirect()->back();
    }

}
