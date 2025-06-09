<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Repositories\Contracts\ClientRepositoryInterface;

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

}
