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

    public function status($status)
    {
        return $this->where('is_active', (bool)$status);
    }

    public function partner($partner)
    {
        return $this->where('partner_id', $partner);
    }

    public function search($search)
    {
        return $this->where('name', 'LIKE', "%{$search}%");
    }

    public function demo($is_demo)
    {
        return $this->where('is_demo', (bool)$is_demo);
    }
}
