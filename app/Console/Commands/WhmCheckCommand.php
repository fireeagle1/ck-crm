<?php

namespace App\Console\Commands;

use App\Exceptions\WhmConnectionException;
use App\Services\WhmCredentials;
use App\Services\WhmService;
use Illuminate\Console\Command;

class WhmCheckCommand extends Command
{
    protected $signature = 'whm:check';

    protected $description = 'Test the WHM connection using the credentials stored in settings (TLS verified)';

    public function handle(WhmService $whm): int
    {
        try {
            $credentials = WhmCredentials::fromSettings();
            $whm->testConnection($credentials->hostname, $credentials->token(), $credentials->username);
        } catch (WhmConnectionException $e) {
            $this->error('WHM check failed: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $this->info("WHM connection OK ({$credentials->hostname}).");

        return Command::SUCCESS;
    }
}
