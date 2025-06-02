<?php

namespace App\Http\Controllers\API;

use App\Models\Invoice;
use App\Services\Payment\InvoiceProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class InvoiceController extends Controller
{
    private InvoiceProvider $invoiceProvider;

    public function __construct(InvoiceProvider $invoiceProvider)
    {
        $this->invoiceProvider = $invoiceProvider;
    }

    /**
     * Скачивание PDF счета
     */
    public function download(Request $request, int $invoiceId): Response
    {
        $token = $request->get('token');

        if (!$token) {
            abort(403, 'Access token required');
        }

        $invoice = Invoice::findOrFail($invoiceId);

        $expectedToken = hash('sha256', $invoiceId . config('app.key') . $invoice->created_at->format('Y-m-d'));

        if (!hash_equals($expectedToken, $token)) {
            abort(403, 'Invalid access token');
        }

        return $this->invoiceProvider->generatePDF($invoice);
    }

}
