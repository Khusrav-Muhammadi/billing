<?php

namespace App\Enums;

enum PartnerStatusEnum: string
{
    case PARTNER = 'partner';
    case AGENT = 'agent';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PARTNER => 'Partner',
            self::AGENT => 'Agent',
        };
    }
}

