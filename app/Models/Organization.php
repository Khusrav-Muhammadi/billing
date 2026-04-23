<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = ['name','INN','phone','email','address', 'client_id', 'has_access', 'business_type_id', 'reject_cause', 'balance', 'license_paid', 'sum_paid_for_license', 'order_number',
        'legal_name', 'legal_address', 'director', 'has_implementation', 'implementation_sum'];

    protected $casts = [
        'balance' => 'decimal:4',
        'sum_paid_for_license' => 'decimal:4',
        'implementation_sum' => 'decimal:4',
        'has_implementation' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(function (self $organization): void {
            if (!empty($organization->order_number)) {
                return;
            }

            DB::transaction(function () use ($organization): void {
                $timestamp = now()->format('YmdHis');
                $random = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

                $organization->forceFill([
                    'order_number' => $timestamp . $random,
                ])->saveQuietly();
            });
        });
    }

    public static function formatOrderNumber(int $number): string
    {
        return str_pad((string) $number, 9, '0', STR_PAD_LEFT);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function packs()
    {
        return $this->hasMany(OrganizationPack::class, 'organization_id');
    }

    public function businessType()
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function history(): MorphMany
    {
        return $this->morphMany('App\Models\ModelHistory', 'model');
    }

    public function balances()
    {
        return $this->hasMany(ClientBalance::class, 'organization_id');
    }

    public function connections()
    {
        return $this->hasMany(OrganizationConnectionStatus::class, 'organization_id');
    }
}
