<?php

namespace App\Events;

use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommercialOfferRenewalPaidStatusEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CommercialOffer $offer,
        public CommercialOfferStatus $status
    ) {
    }
}

