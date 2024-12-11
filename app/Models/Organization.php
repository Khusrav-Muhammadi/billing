<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = ['name','INN','phone','address', 'client_id', 'has_access', 'sale_id', 'tariff_id',];


    public function client()
    {
        return $this
            ->belongsTo(Client::class);
    }
}
