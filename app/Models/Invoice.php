<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = ['payment_provider_id', 'organization_id', 'status','email', 'total_amount', 'currency_id', 'provider', 'operation_type', 'additional_data'];

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
