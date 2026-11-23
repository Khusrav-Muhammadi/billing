<?php

namespace App\Models\PartnerProgram;

use App\Models\DemoRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerLead extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_UNASSIGNED = 'unassigned';

    protected $fillable = ['demo_request_id', 'name', 'phone', 'email', 'status', 'partner_id', 'accepted_at'];

    protected $casts = ['accepted_at' => 'datetime'];

    public function demoRequest(): BelongsTo
    {
        return $this->belongsTo(DemoRequest::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PartnerLeadAssignment::class)->orderBy('id');
    }
}
