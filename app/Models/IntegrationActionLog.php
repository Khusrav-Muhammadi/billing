<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationActionLog extends Model
{
    protected $fillable = [
        'organization_id',
        'client_id',
        'commercial_offer_id',
        'type',
        'action',
        'method',
        'url',
        'recipient',
        'subject',
        'status_code',
        'successful',
        'payload',
        'response',
        'error',
        'occurred_at',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'client_id' => 'integer',
        'commercial_offer_id' => 'integer',
        'status_code' => 'integer',
        'successful' => 'boolean',
        'payload' => 'array',
        'response' => 'array',
        'occurred_at' => 'datetime',
    ];
}
