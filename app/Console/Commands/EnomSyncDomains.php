<?php

namespace App\Console\Commands;

use App\Exceptions\EnomUnavailableException;
use App\Models\ScheduledTaskLog;
use App\Services\EnomClient;
use App\Services\EnomDomainSync;
use App\Services\EnomPage;
use Illuminate\Console\Command;

class EnomSyncDomains extends Command
{
    protected $signature = 'enom:sync {--debug : Show raw API response}';

    protected $description = 'Sync domain list and expiry dates from eNom';

    public function handle(EnomClient $client, EnomDomainSync $sync): int
    {
        $log = ScheduledTaskLog::begin('enom:sync');

        if (! $client->isConfigured()) {
            $log->fail('eNom credentials not configured.');
            $this->error('eNom credentials not configured. Set ENOM_USERNAME and ENOM_PASSWORD in .env');
            return self::FAILURE;
        }

        $this->info('Fetching domain list from eNom...');

        $onPage = $this->option('debug')
            ? function (EnomPage $page, int $start): void {
                $this->line(EnomClient::redact(
                    "Page at {$start}: " . json_encode(['total' => $page->total, 'domains' => $page->domains])
                ));
            }
            : null;

        try {
            $result = $sync->sync($onPage);
        } catch (EnomUnavailableException $e) {
            $log->fail($e->getMessage());
            $this->error($e->getMessage());
            return self::FAILURE;
        } catch (\Throwable $e) {
            $log->fail('eNom sync failed: ' . $e->getMessage());
            $this->error('eNom sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $message = "Synced {$result['fetched']} domains from eNom "
            . "({$result['created']} new, {$result['updated']} updated, {$result['skipped']} skipped, "
            . "{$result['flagged']} flagged missing, {$result['reactivated']} reactivated).";

        $this->info($message);
        $log->complete($message, ['domains_synced' => $result['created'] + $result['updated']] + $result);

        return self::SUCCESS;
    }
}
