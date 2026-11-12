<?php

namespace App\Console\Commands;

use App\Models\ScheduledTaskLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FailStaleHostingCommand extends Command
{
    public const STALE_AFTER_MINUTES = 15;

    protected $signature = 'hosting:fail-stale';

    protected $description = 'Mark hosting services stuck in provisioning "running" for over 15 minutes as failed';

    public function handle(): int
    {
        $log = ScheduledTaskLog::begin('hosting:fail-stale');

        $now = now();
        $updated = DB::table('services')
            ->where('provisioning_status', 'running')
            ->where('provisioning_updated_at', '<', $now->copy()->subMinutes(self::STALE_AFTER_MINUTES))
            ->update([
                'provisioning_status' => 'failed',
                'provisioning_error' => 'Provisioning timed out: no progress for over '.self::STALE_AFTER_MINUTES.' minutes.',
                'provisioning_updated_at' => $now,
            ]);

        $this->info("Failed {$updated} stale provisioning services.");
        Log::info('FailStaleHosting: marked stale services failed', ['count' => $updated]);

        $log->complete("Failed {$updated} stale provisioning services.", ['failed' => $updated]);

        return Command::SUCCESS;
    }
}
