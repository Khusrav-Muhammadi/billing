<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TariffCurrency extends Model
{
    use HasFactory;

    protected $fillable = ['currency_id', 'tariff_id', 'tariff_price', 'license_price'];

    public function tariff()
    {
        return $this->belongsTo(Tariff::class, 'tariff_id');
    }

    /**
     * clients.tariff_id and payment payloads historically mix TariffCurrency PK
     * and Tariff PK. Resolve a priced row, preferring the client's currency.
     */
    public static function resolveById(?int $id, ?int $currencyId = null, ?string $tariffName = null): ?self
    {
        if (!$id) {
            return null;
        }

        $matches = static function (?self $row) use ($currencyId, $tariffName): bool {
            if (!$row) {
                return false;
            }

            if ($currencyId && (int) $row->currency_id !== (int) $currencyId) {
                return false;
            }

            if ($tariffName !== null && $tariffName !== ''
                && strcasecmp((string) $row->tariff?->name, $tariffName) !== 0) {
                return false;
            }

            return true;
        };

        $byPrimaryKey = static::query()->with('tariff')->find($id);
        if ($matches($byPrimaryKey)) {
            return $byPrimaryKey;
        }

        return static::query()
            ->with('tariff')
            ->where('tariff_id', $id)
            ->when($currencyId, fn ($query) => $query->where('currency_id', $currencyId))
            ->get()
            ->first(fn (self $row) => $matches($row));
    }
}
