<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = ['receipt', 'phone', 'cancel_url', 'redirect_url', 'webhook_url', 'timeout', 'invoice_status_id', 'invoice_id', 'organization_id'];
}
