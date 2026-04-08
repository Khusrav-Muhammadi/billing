<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationConnectionStatus extends Model
{
    protected $fillable = [
        'organization_id',
        'status',
        'status_date',
        'commercial_offer_id',
        'day_closing_id',
        'author_id',
        'reason',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'commercial_offer_id' => 'integer',
        'day_closing_id' => 'integer',
        'author_id' => 'integer',
        'status_date' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function commercialOffer(): BelongsTo
    {
        return $this->belongsTo(CommercialOffer::class, 'commercial_offer_id');
    }

    public function dayClosing(): BelongsTo
    {
        return $this->belongsTo(DayClosing::class, 'day_closing_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}

