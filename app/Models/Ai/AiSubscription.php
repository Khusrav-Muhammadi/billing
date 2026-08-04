<?php

namespace App\Models\Ai;

use App\Models\CommercialOffer;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'plan_id',
        'status',
        'period_months',
        'price_paid',
        'started_at',
        'expires_at',
        'last_crm_fetch_at',
        'commercial_offer_id',
    ];

    protected $casts = [
        'status' => 'boolean',
        'price_paid' => 'decimal:4',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_crm_fetch_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AiTariffPlan::class, 'plan_id');
    }

    public function commercialOffer(): BelongsTo
    {
        return $this->belongsTo(CommercialOffer::class);
    }

    public function aiBalance(): HasOne
    {
        return $this->hasOne(AiBalance::class, 'organization_id', 'organization_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
