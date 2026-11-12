<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Single source of truth for the hosting nameservers shown to admins and customers.
 *
 * Requirement 8.5
 */
class Nameservers
{
    public const DEFAULTS = ['ns0.thundercloud.uk', 'ns1.thundercloud.uk'];

    /**
     * Return [ns0, ns1], using settings whm_nameserver_0/1 and falling back to defaults when empty.
     *
     * @return array{0: string, 1: string}
     */
    public static function get(): array
    {
        $result = [];

        foreach (self::DEFAULTS as $i => $default) {
            $value = Setting::get("whm_nameserver_{$i}");
            $value = is_string($value) ? trim($value) : '';
            $result[$i] = $value !== '' ? $value : $default;
        }

        return $result;
    }
}
