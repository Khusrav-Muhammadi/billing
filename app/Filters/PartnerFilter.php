<?php

namespace App\Filters;

use EloquentFilter\ModelFilter;

class PartnerFilter  extends ModelFilter
{
    public function search($search) :self
    {
        $searchTerm = '%' . $search . '%';

        return $this->where(function ($query) use ($searchTerm) {
            $query->orWhere('name', 'like', $searchTerm)
                ->orWhere('email', 'like', $searchTerm)
                ->orWhere('phone', 'like', $searchTerm);
        });
    }
}
