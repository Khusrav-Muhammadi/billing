<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialOfferStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'commercial_offer_id',
        'status',
        'status_date',
        'payment_method',
        'author_id',
        'account_id',
        'payment_order_number',
    ];

    protected $casts = [
        'status_date' => 'date',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(CommercialOffer::class, 'commercial_offer_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
