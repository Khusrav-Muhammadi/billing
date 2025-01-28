<?php

namespace App\Http\Controllers;

use App\Http\Requests\Partner\StoreRequest;
use App\Http\Requests\Partner\UpdateRequest;
use App\Models\Partner;
use App\Models\PartnerRequest;
use App\Models\PartnerStatus;
use App\Models\User;
use App\Repositories\Contracts\PartnerRepositoryInterface;

class PartnerRequestController extends Controller
{
    public function index()
    {
        $partnerRequests = PartnerRequest::all();

        return view('admin.partner_requests.index', compact('partnerRequests'));
    }

}
