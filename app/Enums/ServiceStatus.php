<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case Pending = 'Pending';
    case Active = 'Active';
    case Suspended = 'Suspended';
    case Cancelled = 'Cancelled';

    /** Legacy lower-case aliases mapped to canonical labels. */
    public const ALIASES = [
        'pending' => 'Pending',
        'payment_failed' => 'Suspended',
        'cancelled' => 'Cancelled',
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
