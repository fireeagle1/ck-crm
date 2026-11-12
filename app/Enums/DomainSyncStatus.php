<?php

namespace App\Enums;

enum DomainSyncStatus: string
{
    case Active = 'active';
    case MissingFromEnom = 'missing_from_enom';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
