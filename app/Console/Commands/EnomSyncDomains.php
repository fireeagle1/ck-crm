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
        $this->info("Using: {$baseUrl}");
        $this->info("Username: {$username}");

        $page = 1;
        $totalSynced = 0;
        $errors = [];

        do {
            try {
                $response = Http::timeout(30)->get($baseUrl, [
                    'command' => 'GetDomains',
                    'uid' => $username,
                    'pw' => $password,
                    'responsetype' => 'xml',
                    'Display' => 100,
                    'Page' => $page,
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
                $this->line("Raw response (first 2000 chars):");
                $this->line(substr($body, 0, 2000));
            }

            $xml = @simplexml_load_string($body);

            if (!$xml) {
                $log->fail('Could not parse XML response from eNom.');
                $this->error('Could not parse XML response.');
                if ($this->option('debug')) {
                    $this->line($body);
                }
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

            // eNom returns domains in different formats depending on API version
            // Try the GetDomains response format: DomainName1, DomainName2, etc.
            $domainCount = (int) ($xml->DomainCount ?? 0);

            if ($domainCount > 0) {
                // Format 1: DomainName1, ExpirationDate1, etc.
                for ($i = 1; $i <= $domainCount; $i++) {
                    $domainName = $this->getXmlValue($xml, [
                        "DomainName{$i}",
                        "domainname{$i}",
                    ]);

                    $expiry = $this->getXmlValue($xml, [
                        "ExpirationDate{$i}",
                        "expiration-date{$i}",
                        "ExpDate{$i}",
                    ]);

                    if (!$domainName) continue;

                    Domain::updateOrCreate(
                        ['domain_name' => strtolower(trim($domainName))],
                        [
                            'registrar' => 'eNom',
                            'expiry_date' => $expiry ? date('Y-m-d', strtotime($expiry)) : null,
                        ]
                    );
                    $totalSynced++;
                }
            } else {
                // Format 2: Try <DomainDetail> elements or <domain> elements
                $domainDetails = $xml->xpath('//DomainDetail') ?: $xml->xpath('//domain') ?: [];

                foreach ($domainDetails as $detail) {
                    $domainName = (string) ($detail->DomainName ?? $detail->domainname ?? $detail->Name ?? '');
                    $expiry = (string) ($detail->ExpirationDate ?? $detail->expiration_date ?? $detail->ExpDate ?? '');

                    if (!$domainName) continue;

                    Domain::updateOrCreate(
                        ['domain_name' => strtolower(trim($domainName))],
                        [
                            'registrar' => 'eNom',
                            'expiry_date' => $expiry ? date('Y-m-d', strtotime($expiry)) : null,
                        ]
                    );
                    $totalSynced++;
                }

                // If still 0, log the XML structure for debugging
                if ($totalSynced === 0 && $page === 1) {
                    $children = [];
                    foreach ($xml->children() as $child) {
                        $children[] = $child->getName();
                    }
                    $structureInfo = "XML root children: " . implode(', ', array_slice($children, 0, 30));
                    $this->warn("No domains found in response. {$structureInfo}");
                    $errors[] = $structureInfo;
                }
            }

            $totalPages = (int) ($xml->TotalPages ?? $xml->totalpages ?? 1);
            $page++;
        } while ($page <= $totalPages);

        $this->info("✓ Synced {$totalSynced} domains from eNom.");

        $log->complete("Synced {$totalSynced} domains from eNom.", [
            'domains_synced' => $totalSynced,
            'errors' => $errors,
        ]);

        return self::SUCCESS;
    }

    /**
     * Try multiple XML element names and return the first one that exists.
     */
    private function getXmlValue(\SimpleXMLElement $xml, array $candidates): ?string
    {
        foreach ($candidates as $name) {
            if (isset($xml->$name) && (string) $xml->$name !== '') {
                return (string) $xml->$name;
            }
        }
        return null;
    }
}
