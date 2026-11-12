<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
class WhmImportEnvCredentialsCommand extends Command
{
    protected $signature = 'whm:import-env-credentials';
    protected $description = 'Deprecated: .env-based WHM configuration was removed; credentials live in encrypted settings';
    public function handle(): int
    {
        $this->info('Nothing to import: the .env WHM configuration was removed. Set WHM credentials in Settings, then run whm:check.');
        return Command::SUCCESS;
    }
}
