<?php

namespace App\Services;

use App\Enums\DomainSyncStatus;
use App\Exceptions\EnomUnavailableException;
use App\Models\Domain;
use App\Support\DomainNormaliser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ownership-safe eNom domain sync (Req 13.4 to 13.11, 14.5).
 *
 * All pages are fetched into memory first. Any failed page aborts the run with
 * no database writes and no missing_from_enom flagging. The write phase runs in
 * one transaction and never deletes rows.
 */
class EnomDomainSync
{
    public const PAGE_SIZE = 100;

    public function __construct(private readonly EnomClient $client)
    {
    }

    /**
     * @param  ?callable(EnomPage, int): void  $onPage  Called with each fetched page and its start position.
     * @return array{fetched: int, created: int, updated: int, skipped: int, flagged: int, reactivated: int}
     *
     * @throws EnomUnavailableException when eNom is unreachable or any page fails
     */
    public function sync(?callable $onPage = null): array
    {
        $runStart = CarbonImmutable::now()->startOfSecond();
        $entries = $this->fetchAll($onPage);

        return DB::transaction(fn () => $this->apply($entries, $runStart));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAll(?callable $onPage): array
    {
        $entries = [];
        $start = 1;
        $total = null;

        do {
            $page = $this->client->getDomainsPage($start, self::PAGE_SIZE);

            if (! $page->ok) {
                throw new EnomUnavailableException('eNom returned an invalid or failed page at position ' . $start . '.');
            }

            if ($onPage !== null) {
                $onPage($page, $start);
            }

            $total ??= $page->total;

            if ($page->domains === [] && count($entries) < $total) {
                throw new EnomUnavailableException('eNom returned an empty page before the reported total was reached.');
            }

            foreach ($page->domains as $domain) {
                $entries[] = $domain;
            }

            $start += count($page->domains);
        } while (count($entries) < $total);

        return $entries;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function apply(array $entries, CarbonImmutable $runStart): array
    {
        $result = ['fetched' => count($entries), 'created' => 0, 'updated' => 0, 'skipped' => 0, 'flagged' => 0, 'reactivated' => 0];

        // Index existing rows (including soft-deleted) by canonical name.
        $existing = [];
        foreach (Domain::withTrashed()->get() as $row) {
            $canonical = DomainNormaliser::tryNormalise($row->domain_name) ?? $row->domain_name;
            if (! isset($existing[$canonical]) || $row->domain_name === $canonical) {
                $existing[$canonical] = $row;
            }
        }

        foreach ($entries as $entry) {
            $name = DomainNormaliser::tryNormalise((string) $entry['domain_name']);
            if ($name === null) {
                Log::warning('enom:sync skipped unparseable domain name', ['domain_name' => $entry['domain_name']]);
                $result['skipped']++;
                continue;
            }

            $expiry = $this->parseExpiry((string) $entry['expiration_date']);
            if ($expiry === null) {
                Log::warning('enom:sync could not parse expiry date', ['domain_name' => $name, 'expiration_date' => $entry['expiration_date']]);
            }

            $values = [
                'expiry_date' => $expiry,
                'auto_renew' => (bool) $entry['auto_renew'],
                'registrar' => 'eNom',
                'enom_domain_name_id' => $entry['domain_name_id'] !== '' ? $entry['domain_name_id'] : null,
            ];

            $syncOwned = [
                'enom_response' => json_encode([
                    'domain_name_id' => $entry['domain_name_id'],
                    'auto_renew' => (bool) $entry['auto_renew'],
                    'expiration_date' => $entry['expiration_date'],
                    'synced_at' => $runStart->toIso8601String(),
                ]),
                'last_synced_at' => $runStart,
                'status' => DomainSyncStatus::Active->value,
            ];

            $domain = $existing[$name] ?? null;

            if ($domain === null) {
                $values['expiry_date'] = $expiry;
                $created = Domain::create(['domain_name' => $name, 'company_id' => null] + $values + $syncOwned);
                $existing[$name] = $created;
                $result['created']++;
                continue;
            }

            $edited = $domain->admin_edited_fields ?? [];
            $update = $syncOwned;
            foreach (Domain::ENOM_FIELDS as $field) {
                if (in_array($field, $edited, true)) {
                    continue;
                }
                if ($field === 'expiry_date' && $expiry === null) {
                    continue; // keep the existing expiry
                }
                $update[$field] = $values[$field];
            }

            if ($domain->status === DomainSyncStatus::MissingFromEnom->value) {
                $result['reactivated']++;
            }

            $domain->forceFill($update)->save();
            $result['updated']++;
        }

        $result['flagged'] = Domain::query()
            ->enomSourced()
            ->where('status', DomainSyncStatus::Active->value)
            ->where(function ($q) use ($runStart) {
                $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<', $runStart);
            })
            ->update(['status' => DomainSyncStatus::MissingFromEnom->value]);

        return $result;
    }

    private function parseExpiry(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        foreach (['n/j/Y', 'Y-m-d'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!' . $format, $raw);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }
}
