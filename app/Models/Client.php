<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = ['name','phone','front_sub_domain','back_sub_domain','business_type_id','organization','INN', 'address', 'balance'];

    public function businessType()
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function tariff()
    {
        return $this->belongsTo(Tariff::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
