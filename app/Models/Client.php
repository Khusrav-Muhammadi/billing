<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = ['name','phone','project_uuid','front_sub_domain','back_sub_domain','business_type_id','organization','INN','license', 'address', 'sale_id'];

    public function businessType()
    {
        return $this->belongsTo(BusinessType::class);
    }
}
