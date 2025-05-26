<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\InvoiceRequest;
use App\Repositories\Payment\Contracts\PaymentRepositoryInterface;
use Illuminate\Http\Request;

class PaymentController extends Controller
{

    public function __construct(public PaymentRepositoryInterface $repository) { }


    public function createInvoice(InvoiceRequest $request)
    {dd(3);
        $data = $request->validated();
        $data['sub_domain'] = parse_url($request->fullUrl(), PHP_URL_HOST);
        return $this->repository->createInvoice($data);
    }

    public function webhook(Request $request)
    {
        return $this->repository->webhook($request);
    }

}
