<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    use HasFactory;

    protected $fillable = ['tariff_id', 'organization_id', 'start_date', 'date', 'sum', 'currency_id', 'kind'];

    protected $casts = [
        'sum' => 'decimal:4',
        'currency_id' => 'integer',
        'tariff_id' => 'integer',
        'organization_id' => 'integer',
    ];

    public function tariff()
    {
        return $this->belongsTo(Tariff::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
