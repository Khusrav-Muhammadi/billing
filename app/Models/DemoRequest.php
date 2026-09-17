<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Заявка на демо-доступ с сайта.
 *
 * Выдача демо занимает от нескольких секунд до пары минут (поддомен + база
 * тенанта на стороне CRM), поэтому она не укладывается в один HTTP-запрос:
 * сайт создаёт заявку, а затем опрашивает её статус.
 */
class DemoRequest extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROVISIONING = 'provisioning';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    /** Шаги в том порядке, в котором их показывает сайт. */
    public const STEP_ACCOUNT = 'account';
    public const STEP_WORKSPACE = 'workspace';
    public const STEP_CRM = 'crm';
    public const STEP_DONE = 'done';

    public const STEPS = [
        self::STEP_ACCOUNT,
        self::STEP_WORKSPACE,
        self::STEP_CRM,
        self::STEP_DONE,
    ];

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'phone',
        'country_id',
        'partner_id',
        'manager_id',
        'status',
        'step',
        'sub_domain',
        'client_id',
        'organization_id',
        'login_url',
        'login_url_expires_at',
        'failure_code',
        'failure_reason',
        'ip',
        'user_agent',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'login_url_expires_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $request) {
            $request->uuid ??= (string) Str::uuid();
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_PROVISIONING], true);
    }


    public function hasUsableLoginUrl(): bool
    {
        return $this->status === self::STATUS_READY
            && filled($this->login_url)
            && $this->login_url_expires_at?->isFuture();
    }

    public function markStep(string $step): void
    {
        $this->update([
            'status' => self::STATUS_PROVISIONING,
            'step' => $step,
            'started_at' => $this->started_at ?? now(),
        ]);
    }

    public function markReady(string $loginUrl, int $tokenTtlMinutes): void
    {
        $this->update([
            'status' => self::STATUS_READY,
            'step' => self::STEP_DONE,
            'login_url' => $loginUrl,
            'login_url_expires_at' => now()->addMinutes($tokenTtlMinutes),
            'failure_code' => null,
            'failure_reason' => null,
            'finished_at' => now(),
        ]);
    }

    public function markFailed(string $code, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failure_code' => $code,
            'failure_reason' => $reason,
            'finished_at' => now(),
        ]);
    }

    /** Номер текущего шага, 1-based, для прогресса на сайте. */
    public function stepNumber(): int
    {
        $index = array_search($this->step, self::STEPS, true);

        return $index === false ? 1 : $index + 1;
    }
}
