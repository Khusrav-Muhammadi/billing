<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommercialOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'partner_id',
        'tariff_id',
        'payment_id',
        'created_by',
        'status',
        'request_type',
        'saved_at',
        'locked_at',
        'status_date',
        'pricing_date',
        'currency',
        'payable_currency',
        'card_payment_type',
        'period_months',
        'extra_users',
        'client_name',
        'client_phone',
        'client_email',
        'partner_name',
        'partner_phone',
        'partner_email',
        'payer_type',
        'manager_name',
        'original_total',
        'monthly_total',
        'period_total',
        'grand_total',
        'payable_total',
        'conversion_rate',
        'payment_link',
    ];

    protected $casts = [
        'saved_at' => 'datetime',
        'locked_at' => 'datetime',
        'status_date' => 'date',
        'pricing_date' => 'date',
        'original_total' => 'decimal:2',
        'monthly_total' => 'decimal:2',
        'period_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'payable_total' => 'decimal:2',
        'conversion_rate' => 'decimal:6',
    ];

    public function items()
    {
        return $this->hasMany(CommercialOfferItem::class);
    }

    public function services()
    {
        return $this->items();
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function tariff()
    {
        return $this->belongsTo(Tariff::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function offerStatuses()
    {
        return $this->hasMany(CommercialOfferStatus::class)->orderByDesc('status_date')->orderByDesc('id');
    }

    public function latestOfferStatus()
    {
        return $this->hasOne(CommercialOfferStatus::class)->latestOfMany('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
