<?php

namespace App\Support;

use App\Exceptions\InvalidDomainException;

/**
 * Pure domain canonicaliser: trim, lowercase, strip trailing dots and leading "www.",
 * convert to ASCII (punycode), then validate. Idempotent on its own output.
 */
final class DomainNormaliser
{
    /** @throws InvalidDomainException */
    public static function normalise(string $input): string
    {
        $d = mb_strtolower(trim($input), 'UTF-8');
        $d = rtrim($d, '.');
        while (str_starts_with($d, 'www.')) {
            $d = substr($d, 4);
        }
        if ($d === '') {
            throw new InvalidDomainException($input, 'empty');
        }

        $ascii = idn_to_ascii($d, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
        if ($ascii === false || $ascii === '') {
            throw new InvalidDomainException($input, 'IDNA conversion failed');
        }
        $ascii = strtolower($ascii);

        if (strlen($ascii) > 253) {
            throw new InvalidDomainException($input, 'too long');
        }
        $labels = explode('.', $ascii);
        if (count($labels) < 2) {
            throw new InvalidDomainException($input, 'needs at least two labels');
        }
        foreach ($labels as $label) {
            if (!preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)$/D', $label)) {
                throw new InvalidDomainException($input, 'bad label');
            }
        }
        $tld = end($labels);
        if (!preg_match('/^[a-z]{2,}$/D', $tld) && !str_starts_with($tld, 'xn--')) {
            throw new InvalidDomainException($input, 'bad TLD');
        }

        return $ascii;
    }

    public static function tryNormalise(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        try {
            return self::normalise($input);
        } catch (InvalidDomainException) {
            return null;
        }
    }

    public static function toHttpsUrl(string $canonical): string
    {
        return 'https://' . $canonical;
    }
}
