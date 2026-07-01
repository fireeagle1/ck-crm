<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\ScheduledTaskLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class EnomSyncDomains extends Command
{
    protected $signature = 'enom:sync';

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

        $page = 1;
        $totalSynced = 0;

        do {
            $response = Http::get($baseUrl, [
                'command' => 'GetDomains',
                'uid' => $username,
                'pw' => $password,
                'responsetype' => 'xml',
                'Display' => 100,
                'Page' => $page,
            ]);

            if (!$response->ok()) {
                $this->error("eNom API returned HTTP {$response->status()}");
                return self::FAILURE;
            }

            $xml = simplexml_load_string($response->body());

            if (!$xml || (string) ($xml->ErrCount ?? '0') !== '0') {
                $this->error('eNom API error: ' . ($xml->errors->Err1 ?? 'Unknown'));
                return self::FAILURE;
            }

            $domainCount = (int) ($xml->DomainCount ?? 0);

            // Parse domains from the eNom response format
            for ($i = 1; $i <= $domainCount; $i++) {
                $nameKey = "DomainName{$i}";
                $expKey = "ExpirationDate{$i}";

                $domainName = (string) ($xml->$nameKey ?? '');
                $expiry = (string) ($xml->$expKey ?? '');

                if (!$domainName) {
                    continue;
                }

                Domain::updateOrCreate(
                    ['domain_name' => strtolower($domainName)],
                    [
                        'registrar' => 'eNom',
                        'expiry_date' => $expiry ? date('Y-m-d', strtotime($expiry)) : null,
                        'enom_response' => json_encode([
                            'synced_at' => now()->toIso8601String(),
                        ]),
                    ]
                );

                $totalSynced++;
            }

            // Check if there are more pages
            $totalPages = (int) ($xml->TotalPages ?? 1);
            $page++;
        } while ($page <= $totalPages);

        $this->info("✓ Synced {$totalSynced} domains from eNom.");

        $log->complete("Synced {$totalSynced} domains from eNom.", [
            'domains_synced' => $totalSynced,
        ]);

        return self::SUCCESS;
    }
}
