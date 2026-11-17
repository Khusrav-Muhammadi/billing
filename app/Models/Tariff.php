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

    /**
     * Подключение Telegram-аккаунта закрыто: доп. канал и отдельная строка интеграции.
     * Списки «WhatsApp / Instagram / Telegram» сюда не входят — из них убирается только Telegram.
     */
    public static function isDedicatedTelegramChannelLine(?string $name): bool
    {
        $name = mb_strtolower(str_replace(['Ё', 'ё'], ['Е', 'е'], trim((string) $name)), 'UTF-8');
        if ($name === '' || str_contains($name, 'mini')) {
            return false;
        }

        $isTelegram = str_contains($name, 'telegram') || str_contains($name, 'телеграм');
        if (!$isTelegram) {
            return false;
        }

        if (str_contains($name, 'whatsapp') || str_contains($name, 'instagram') || str_contains($name, 'messenger')) {
            return false;
        }

        return str_contains($name, 'канал')
            || str_contains($name, 'аккаунт')
            || str_contains($name, 'интеграц')
            || str_contains($name, 'подключ');
    }

    public static function stripTelegramFromIntegrationText(string $text): string
    {
        if (!preg_match('/telegram|телеграм/iu', $text) || preg_match('/mini/iu', $text)) {
            return $text;
        }

        if (!preg_match('/whatsapp|instagram|messenger/iu', $text)) {
            return $text;
        }

        $updated = preg_replace('#\s*/\s*Telegram\b#iu', '', $text) ?? $text;
        $updated = preg_replace('#\bTelegram\s*/\s*#iu', '', $updated) ?? $updated;
        $updated = preg_replace('#,\s*Telegram\b#iu', '', $updated) ?? $updated;
        $updated = preg_replace('#\s+и\s+Telegram\b#iu', '', $updated) ?? $updated;
        $updated = preg_replace('#\bTelegram\s+и\s+#iu', '', $updated) ?? $updated;

        return trim(preg_replace('/\s{2,}/u', ' ', $updated) ?? $updated);
    }

    public static function sanitizeOfferLines(array $lines): array
    {
        $result = [];

        foreach ($lines as $line) {
            if (is_string($line)) {
                if (self::isDedicatedTelegramChannelLine($line)) {
                    continue;
                }
                $result[] = self::stripTelegramFromIntegrationText($line);
                continue;
            }

            if (!is_array($line)) {
                $result[] = $line;
                continue;
            }

            $label = (string) ($line['name'] ?? $line['title'] ?? '');
            if (self::isDedicatedTelegramChannelLine($label)) {
                continue;
            }

            foreach (['name', 'title'] as $key) {
                if (isset($line[$key]) && is_string($line[$key])) {
                    $line[$key] = self::stripTelegramFromIntegrationText($line[$key]);
                }
            }

            $result[] = $line;
        }

        return array_values($result);
    }

    public static function sanitizeTariffFeatures(array $features): array
    {
        if (isset($features['socialIntegrations']) && is_array($features['socialIntegrations'])) {
            unset($features['socialIntegrations']['telegram']);
        }

        return $features;
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
