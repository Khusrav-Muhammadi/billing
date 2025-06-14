<?php

namespace App\Filters;

use EloquentFilter\ModelFilter;

class ClientFilter  extends ModelFilter
{
    public function type($type)
    {
        return $this->where('type', $type);
    }

    public function tariff($tarif)
    {
        return $this->where('tariff_id', $tarif);
    }

    public function status($status) :self
    {
        return $this->where('is_active', (bool)$status);
    }

    public function partner($partner) :self
    {
        return $this->where('partner_id', $partner);
    }

    public function currency($currencyId) :self
    {
        return $this->where('currency_id', $currencyId);
    }

    public function country($countryId) :self
    {
        return $this->where('country_id', $countryId);
    }

    public function search($search) :self
    {
        $searchTerm = '%' . $search . '%';

        return $this->where(function ($query) use ($searchTerm) {
            $query->orWhere('name', 'like', $searchTerm)
                ->orWhere('email', 'like', $searchTerm)
                ->orWhere('phone', 'like', $searchTerm);
        });
    }

    public function demo($is_demo) :self
    {
        return $this->where('is_demo', (bool)$is_demo);
    }
}
