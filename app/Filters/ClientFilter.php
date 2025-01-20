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
}
