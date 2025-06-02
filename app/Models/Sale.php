<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sale_type',
        'amount',
        'apply_to',
        'min_months',
        'is_active',
        'start_date',
        'end_date',
    ];

    public function isActive(): bool
    {
        if (!$this->is_active) return false;

        $now = now();
        return (!$this->start_date || $now >= $this->start_date)
            && (!$this->end_date || $now <= $this->end_date);
    }
}
