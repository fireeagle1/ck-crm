<?php

namespace App\Console\Commands;

use App\Models\ProcessedWebhookEvent;
use App\Models\ScheduledTaskLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeWebhookEventsCommand extends Command
{
    protected $signature = 'app:purge-webhook-events';

    protected $description = 'Delete processed webhook events older than 7 days';

    public function handle(): int
    {
        $log = ScheduledTaskLog::begin('app:purge-webhook-events');

        $deleted = ProcessedWebhookEvent::where('created_at', '<', now()->subDays(7))->delete();

        $this->info("Purged {$deleted} webhook event records.");
        Log::info('PurgeWebhookEvents: Purged old records', ['count' => $deleted]);

        $log->complete("Purged {$deleted} webhook event records.", ['deleted' => $deleted]);

        return Command::SUCCESS;
    }
}
