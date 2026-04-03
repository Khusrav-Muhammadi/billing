<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DayClosingClientDetails extends Model
{
    protected $fillable = ['day_closing_id', 'organization_id', 'currency_id', 'balance_before_accrual', 'balance_after_accrual', 'status_after_accrual'];

}
