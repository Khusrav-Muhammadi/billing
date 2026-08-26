<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'user_count',
        'project_count',
        'is_tariff',
        'is_extra_user',
        'parent_tariff_id',
        'partner_id',
        'currency_id',
        'sale',
        'end_date',
        'can_increase',
        'is_external',
        'is_public',
        'category',
        'is_one_time',
        'one_time_label',
        'type'
    ];

    protected $casts = [
        'end_date' => 'date',
        'can_increase' => 'bool',
        'is_external' => 'bool',
        'is_public' => 'bool',
        'is_one_time' => 'bool',
    ];

    public function currencies()
    {
        return $this->hasMany(TariffCurrency::class);
    }

    public function prices()
    {
        return $this->hasMany(Price::class);
    }

    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function includedServices()
    {
        return $this->belongsToMany(Tariff::class, 'tariff_included_services', 'tariff_id', 'service_id')
            ->withPivot(['quantity', 'is_paid'])
            ->withTimestamps();
    }

    public function excludedOrganizations()
    {
        return $this->belongsToMany(Organization::class, 'tariff_exclusions', 'tariff_id', 'organization_id')
            ->withTimestamps();
    }

    public function exclusions()
    {
        return $this->excludedOrganizations();
    }

    /**
     * Базовый тариф CRM (по имени). На нём ИИ-агент недоступен.
     */
    public static function isBaseTariffName(?string $name): bool
    {
        $normalized = mb_strtolower(str_replace('ё', 'е', trim((string) $name)), 'UTF-8');
        $compact = preg_replace('/[^a-z0-9а-я]+/u', '', $normalized) ?: '';

        return $compact === 'base'
            || $compact === 'basic'
            || str_contains($compact, 'базов')
            || str_contains($compact, 'баз')
            || str_contains($compact, 'base');
    }

    public function isBaseTariff(): bool
    {
        return self::isBaseTariffName($this->name);
    }
}
