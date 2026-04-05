<?php

namespace App\Support;

use App\Models\Currency;

class CurrencyResolver
{
    /**
     * @var array<string,int|null>
     */
    private static array $idByCode = [];

    public static function idFromCode(?string $code): ?int
    {
        $normalized = strtoupper(trim((string) $code));
        if ($normalized === '') {
            return null;
        }

        // Legacy aliases.
        if ($normalized === 'UZB') {
            $normalized = 'UZS';
        }

        if (array_key_exists($normalized, self::$idByCode)) {
            return self::$idByCode[$normalized];
        }

        $id = Currency::query()
            ->whereRaw('UPPER(symbol_code) = ?', [$normalized])
            ->value('id');

        self::$idByCode[$normalized] = $id !== null ? (int) $id : null;

        return self::$idByCode[$normalized];
    }
}
