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
        'type',
        // GB хранилища: для тарифа — включено, для услуги add_storage — за одну единицу.
        'storage_gb',
    ];

    /** Тип услуги «доп. пакет хранилища» (см. миграцию add_storage_gb_to_tariffs_table). */
    public const TYPE_ADD_STORAGE = 'add_storage';

    /** «Тариф учет» в подключении показывается только этому партнёру (FIN GROUP). */
    public const FINGROUP_PARTNER_ID = 8;

    public const FINGROUP_ACCOUNTING_TARIFF_NAME = 'тариф учет';

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

    public static function isFingroupAccountingTariffName(?string $name): bool
    {
        $normalized = mb_strtolower(str_replace(['Ё', 'ё'], ['Е', 'е'], trim((string) $name)), 'UTF-8');

        return $normalized === self::FINGROUP_ACCOUNTING_TARIFF_NAME;
    }

    /**
     * Партнёр, которому виден тариф в подключении.
     * «Тариф учет» всегда только FIN GROUP, даже если в базе стоит другой partner_id.
     */
    public function commercialPartnerId(): ?int
    {
        if (self::isFingroupAccountingTariffName($this->name)) {
            return self::FINGROUP_PARTNER_ID;
        }

        return $this->partner_id ? (int) $this->partner_id : null;
    }

    public function scopeForCommercialPartner($query, ?int $partnerId)
    {
        if ($partnerId === null) {
            return $query;
        }

        $query->where(function ($query) use ($partnerId) {
            $query->whereNull('partner_id')
                ->orWhere('partner_id', $partnerId);

            if ($partnerId === self::FINGROUP_PARTNER_ID) {
                $query->orWhereRaw(
                    "LOWER(REPLACE(REPLACE(name, 'Ё', 'Е'), 'ё', 'е')) = ?",
                    [self::FINGROUP_ACCOUNTING_TARIFF_NAME]
                );
            }
        });

        if ($partnerId !== self::FINGROUP_PARTNER_ID) {
            $query->whereRaw(
                "LOWER(REPLACE(REPLACE(name, 'Ё', 'Е'), 'ё', 'е')) != ?",
                [self::FINGROUP_ACCOUNTING_TARIFF_NAME]
            );
        }

        return $query;
    }
}
