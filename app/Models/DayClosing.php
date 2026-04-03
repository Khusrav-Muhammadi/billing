<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DayClosing extends Model
{
    use SoftDeletes;
    protected $table = 'day_closing_details';

    protected $fillable = ['date', 'doc_number', 'author_id', 'client_amount', 'status'];
}
