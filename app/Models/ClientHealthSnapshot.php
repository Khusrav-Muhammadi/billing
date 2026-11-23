<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Индекс здоровья клиента за день. См. App\Services\ClientHealth\ClientHealthService.
 */
class ClientHealthSnapshot extends Model
{
    public const GREEN = 'green';
    public const YELLOW = 'yellow';
    public const RED = 'red';
    public const GREY = 'grey';

    /** Порядок в списке: красные сверху. */
    public const COLOR_ORDER = [self::RED, self::YELLOW, self::GREY, self::GREEN];

    protected $fillable = [
        'client_id', 'date', 'client_type',
        'logins_score', 'coverage_score', 'dialogs_score', 'deals_score', 'health_index',
        'color', 'reasons', 'reason_text', 'paid_until', 'period_started_at', 'metrics',
    ];

    protected $casts = [
        'date' => 'date',
        'paid_until' => 'date',
        'period_started_at' => 'date',
        'reasons' => 'array',
        'metrics' => 'array',
        'logins_score' => 'float',
        'coverage_score' => 'float',
        'dialogs_score' => 'float',
        'deals_score' => 'float',
        'health_index' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
