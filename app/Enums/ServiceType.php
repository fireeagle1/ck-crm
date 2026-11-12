<?php

namespace App\Enums;

enum ServiceType: string
{
    case TechnicalSupport = 'Technical Support';
    case WebHosting = 'Web Hosting';
    case Other = 'Other';

    /** Legacy lower-case aliases mapped to canonical labels. */
    public const ALIASES = [
        'hosting' => 'Web Hosting',
    ];

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Case/whitespace-insensitive match on labels plus legacy aliases. Null when no match. */
    public static function fromLoose(?string $raw): ?self
    {
        if ($raw === null) {
            return null;
        }

        $key = mb_strtolower(trim($raw));

        if (isset(self::ALIASES[$key])) {
            return self::from(self::ALIASES[$key]);
        }

        foreach (self::cases() as $case) {
            if (mb_strtolower($case->value) === $key) {
                return $case;
            }
        }

        return null;
    }
}
