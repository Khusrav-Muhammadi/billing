<?php

namespace App\Models\PartnerProgram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerLeadAssignment extends Model
{
    public const ACTIVE = 'active';
    public const ACCEPTED = 'accepted';
    public const EXPIRED = 'expired';
    public const FAILED = 'failed';

    protected $fillable = [
        'partner_lead_id', 'partner_id', 'client_id', 'crm_lead_id', 'crm_created', 'outcome',
        'assigned_at', 'deadline_at', 'resolved_at', 'activity', 'error',
    ];

    protected $casts = [
        'crm_created' => 'boolean',
        'assigned_at' => 'datetime',
        'deadline_at' => 'datetime',
        'resolved_at' => 'datetime',
        'activity' => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(PartnerLead::class, 'partner_lead_id');
    }
}
