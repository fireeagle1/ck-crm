<?php

namespace App\Support;

use App\Exceptions\InvalidDomainException;
use Generator;

/**
 * Pure cPanel username generator: lowercase [a-z0-9], starts with a letter,
 * length 3..hosting.username_max_length, not reserved. Numeric suffixes replace
 * the tail of the base so the length cap always holds.
 */
final class CpanelUsername
{
    private const MIN_LENGTH = 3;

    private const MAX_SUFFIX = 999;

    public static function maxLength(): int
    {
        return max(self::MIN_LENGTH + 1, (int) config('hosting.username_max_length', 8));
    }

    /** @return list<string> */
    private static function reserved(): array
    {
        $list = config('hosting.username_reserved');
        if (! is_array($list)) {
            $list = ['root', 'admin', 'mysql', 'test'];
        }

        return array_map('strtolower', $list);
    }

    public static function isValid(string $name): bool
    {
        $len = strlen($name);

        return $len >= self::MIN_LENGTH
            && $len <= self::maxLength()
            && preg_match('/^[a-z][a-z0-9]*$/', $name) === 1
            && ! in_array($name, self::reserved(), true);
    }

    /** Yields base, base2, base3, ... every value valid and distinct. */
    public static function candidates(string $domain): Generator
    {
        $max = self::maxLength();
        $base = self::base($domain, $max);
        $seen = [];

        if (self::isValid($base)) {
            $seen[$base] = true;
            yield $base;
        }

        for ($n = 2; $n <= self::MAX_SUFFIX; $n++) {
            $suffix = (string) $n;
            $keep = $max - strlen($suffix);
            if ($keep < 1) {
                return;
            }
            $candidate = substr($base, 0, $keep).$suffix;
            if (isset($seen[$candidate]) || ! self::isValid($candidate)) {
                continue;
            }
            $seen[$candidate] = true;
            yield $candidate;
        }
    }

    private static function base(string $domain, int $max): string
    {
        try {
            $canonical = DomainNormaliser::normalise($domain);
        } catch (InvalidDomainException) {
            $canonical = mb_strtolower(trim($domain), 'UTF-8');
        }

        $label = explode('.', $canonical)[0];
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($label)) ?? '';

        if ($base === '') {
            $base = 'site';
        }
        if (! ctype_alpha($base[0])) {
            $base = 'c'.$base;
        }
        $base = substr($base, 0, $max);
        if (strlen($base) < self::MIN_LENGTH) {
            $base = str_pad($base, self::MIN_LENGTH, '0');
        }

        return $base;
    }
}
