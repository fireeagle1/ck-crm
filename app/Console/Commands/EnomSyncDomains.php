<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\ScheduledTaskLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class EnomSyncDomains extends Command
{
    protected $signature = 'enom:sync {--debug : Show raw API response}';

    protected $description = 'Sync domain list and expiry dates from eNom';

    public function handle(): int
    {
        $log = ScheduledTaskLog::begin('enom:sync');

        $username = config('services.enom.username');
        $password = config('services.enom.password');
        $sandbox = config('services.enom.sandbox', false);

        if (!$username || !$password) {
            $log->fail('eNom credentials not configured.');
            $this->error('eNom credentials not configured. Set ENOM_USERNAME and ENOM_PASSWORD in .env');
            return self::FAILURE;
        }

        $baseUrl = $sandbox
            ? 'https://resellertest.enom.com/interface.asp'
            : 'https://reseller.enom.com/interface.asp';

        $this->info('Fetching domain list from eNom...');

        $totalSynced = 0;
        $errors = [];

        try {
            $response = Http::timeout(30)->get($baseUrl, [
                'command' => 'GetDomains',
                'uid' => $username,
                'pw' => $password,
                'responsetype' => 'xml',
                'Display' => 200,
                'Page' => 1,
            ]);
        } catch (\Exception $e) {
            $log->fail("HTTP request failed: {$e->getMessage()}");
            $this->error("HTTP request failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        if (!$response->ok()) {
            $log->fail("eNom API returned HTTP {$response->status()}");
            $this->error("eNom API returned HTTP {$response->status()}");
            return self::FAILURE;
        }

        $body = $response->body();

        if ($this->option('debug')) {
            $this->line(substr($body, 0, 3000));
        }

        $xml = @simplexml_load_string($body);

        if (!$xml) {
            $log->fail('Could not parse XML response from eNom.');
            $this->error('Could not parse XML response.');
            return self::FAILURE;
        }

        // Check for API errors
        $errCount = (int) ($xml->ErrCount ?? 0);
        if ($errCount > 0) {
            $errMsg = (string) ($xml->errors->Err1 ?? $xml->Err1 ?? 'Unknown error');
            $log->fail("eNom API error: {$errMsg}");
            $this->error("eNom API error: {$errMsg}");
            return self::FAILURE;
        }

        // Parse the actual eNom format:
        // <GetDomains><domain-list><domain><sld>example</sld><tld>co.uk</tld><expiration-date>9/9/2026</expiration-date>...
        $getDomains = $xml->GetDomains ?? null;

        if (!$getDomains) {
            $log->fail('No GetDomains element in response.');
            $this->error('Unexpected response structure — no GetDomains element.');
            return self::FAILURE;
        }

        $domainList = $getDomains->{'domain-list'} ?? null;

        if (!$domainList) {
            $log->complete('No domain-list element found.', ['domains_synced' => 0]);
            $this->warn('No domain-list element in response.');
            return self::SUCCESS;
        }

        foreach ($domainList->domain as $domainNode) {
            $sld = trim((string) ($domainNode->sld ?? ''));
            $tld = trim((string) ($domainNode->tld ?? ''));
            $expirationDate = trim((string) ($domainNode->{'expiration-date'} ?? ''));
            $autoRenew = (string) ($domainNode->{'auto-renew'} ?? '0');
            $domainNameId = (string) ($domainNode->DomainNameID ?? '');

            if (!$sld || !$tld) {
                continue;
            }

            $fullDomain = strtolower("{$sld}.{$tld}");

            // Parse the date (format: M/D/YYYY e.g. 9/9/2026)
            $expiryDate = null;
            if ($expirationDate) {
                $parsed = date_create_from_format('n/j/Y', $expirationDate)
                       ?: date_create_from_format('m/d/Y', $expirationDate)
                       ?: date_create($expirationDate);

                if ($parsed) {
                    $expiryDate = $parsed->format('Y-m-d');
                }
            }

            Domain::updateOrCreate(
                ['domain_name' => $fullDomain],
                [
                    'registrar' => 'eNom',
                    'expiry_date' => $expiryDate,
                    'auto_renew' => $autoRenew === '1',
                    'enom_response' => json_encode([
                        'domain_name_id' => $domainNameId,
                        'auto_renew' => $autoRenew === '1',
                        'synced_at' => now()->toIso8601String(),
                    ]),
                ]
            );

            $totalSynced++;
            $this->line("  ✓ {$fullDomain} — expires {$expiryDate}" . ($autoRenew === '1' ? ' (auto-renew)' : ''));
        }

        $this->info("✓ Synced {$totalSynced} domains from eNom.");

        $log->complete("Synced {$totalSynced} domains from eNom.", [
            'domains_synced' => $totalSynced,
        ]);

        return self::SUCCESS;
    }
}
