<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = ['name','price', 'invoice_id', 'amount', 'purpose', 'sale_id'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
