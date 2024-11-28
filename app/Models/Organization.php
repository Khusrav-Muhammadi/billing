<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = ['name','INN','license','phone','address','sale_id','client_id'];


    public function client()
    {
        return $this
            ->belongsTo(Client::class);
    }
}
