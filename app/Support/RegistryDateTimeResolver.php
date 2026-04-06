<?php

namespace App\Support;

use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use Illuminate\Support\Carbon;

class RegistryDateTimeResolver
{
    public static function resolve(CommercialOffer $offer, CommercialOfferStatus $status): Carbon
    {
        $baseDate = $offer->status_date
            ?: $status->status_date
            ?: now();

        $timeSource = $status->created_at ?: now();

        $date = Carbon::parse($baseDate);
        $time = Carbon::parse($timeSource);

        return $date->setTime($time->hour, $time->minute, $time->second);
    }
}

