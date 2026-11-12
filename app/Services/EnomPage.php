<?php

namespace App\Services;

/**
 * One page of eNom GetDomains results.
 *
 * Each domain is an array with keys: domain_name_id, sld, tld, domain_name,
 * expiration_date (raw string as sent by eNom) and auto_renew (bool).
 */
final class EnomPage
{
    /**
     * @param  array<int, array{domain_name_id: string, sld: string, tld: string, domain_name: string, expiration_date: string, auto_renew: bool}>  $domains
     */
    public function __construct(
        public readonly array $domains,
        public readonly int $total,
        public readonly bool $ok,
    ) {
    }
}
