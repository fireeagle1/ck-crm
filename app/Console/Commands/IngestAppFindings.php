<?php

namespace App\Console\Commands;

use App\Exceptions\InvalidFindingsPayloadException;
use App\Exceptions\UnknownCustomerAppException;
use App\Models\ScheduledTaskLog;
use App\Services\FindingsIngestService;
use Illuminate\Console\Command;

/**
 * Manually push a findings payload (packages + security findings) for one app.
 *
 * This is the CLI half of the findings receiving stub. It reads a local JSON
 * file and delegates to {@see FindingsIngestService}; it contains NO GitHub,
 * ChatGPT, or OpenAI logic and is deliberately NOT registered in the scheduler
 * — it exists only so the future daily sync, or a human operator, can invoke
 * ingestion from the command line (Req 3.1, 3.6).
 *
 * The JSON file is expected to hold an object of the shape:
 *   { "packages": [ { "name": "...", "version": "..." }, ... ],
 *     "findings":  [ { "severity": "...", "description": "...", "status": "..." }, ... ] }
 */
class IngestAppFindings extends Command
{
    protected $signature = 'apps:ingest-findings
        {app : The CustomerApp identifier to ingest findings for}
        {--file= : Path to a JSON file containing {packages:[...], findings:[...]}}';

    protected $description = 'Ingest a local packages + security findings payload for one customer app';

    public function handle(FindingsIngestService $ingest): int
    {
        $log = ScheduledTaskLog::begin('apps:ingest-findings', [
            'app' => (string) $this->argument('app'),
        ]);

        $path = $this->option('file');

        if (empty($path)) {
            $log->fail('No --file provided.');
            $this->error('A --file path to a JSON payload is required.');
            return self::FAILURE;
        }

        if (! is_file($path) || ! is_readable($path)) {
            $log->fail("File not found or unreadable: {$path}");
            $this->error("File not found or unreadable: {$path}");
            return self::FAILURE;
        }

        $raw = file_get_contents($path);
        $payload = json_decode((string) $raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($payload)) {
            $message = 'Invalid JSON in payload file: ' . json_last_error_msg();
            $log->fail($message);
            $this->error($message);
            return self::FAILURE;
        }

        $app = (string) $this->argument('app');
        $packages = is_array($payload['packages'] ?? null) ? $payload['packages'] : [];
        $findings = is_array($payload['findings'] ?? null) ? $payload['findings'] : [];

        $this->info("Ingesting findings for app [{$app}] from {$path}...");

        try {
            $counts = $ingest->ingest($app, $packages, $findings);
        } catch (UnknownCustomerAppException $e) {
            $log->fail($e->getMessage());
            $this->error($e->getMessage());
            return self::FAILURE;
        } catch (InvalidFindingsPayloadException $e) {
            $log->fail($e->getMessage());
            $this->error('Payload failed validation:');
            foreach ($e->errors() as $error) {
                $this->line("  - {$error}");
            }
            return self::FAILURE;
        }

        $summary = "Ingested {$counts['packages']} package(s), {$counts['findings']} finding(s).";
        $this->info("✓ {$summary}");

        $log->complete($summary, [
            'packages_ingested' => $counts['packages'],
            'findings_ingested' => $counts['findings'],
        ]);

        return self::SUCCESS;
    }
}
