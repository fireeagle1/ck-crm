<?php

namespace App\Services;

use App\Exceptions\InvalidFindingsPayloadException;
use App\Exceptions\UnknownCustomerAppException;
use App\Models\CustomerApp;
use App\Models\SecurityFinding;
use Illuminate\Support\Facades\DB;

/**
 * Receives a payload of App_Packages and Security_Findings for one identified
 * app and bulk-replaces that app's stored records in a single transaction.
 *
 * This is the storage half of the findings receiving stub. It contains NO
 * GitHub, ChatGPT, or OpenAI logic — the future daily sync runs its scan
 * elsewhere and delivers its results here (Req 3.6).
 *
 * Identifier resolution: the design leaves {identifier} open as "the CustomerApp
 * id, or service_id". This implementation resolves canonically by the
 * CustomerApp primary key (`id`) so resolution is deterministic and unambiguous
 * — service_id and id share an integer shape and could otherwise collide.
 */
class FindingsIngestService
{
    /**
     * Validate, resolve and bulk-replace an app's packages and findings.
     *
     * @param string $appIdentifier The CustomerApp primary key (`id`).
     * @param array<int, array<string, mixed>> $packages Each: name, version.
     * @param array<int, array<string, mixed>> $findings Each: severity, description, status.
     *
     * @return array{packages:int, findings:int} Counts persisted.
     *
     * @throws UnknownCustomerAppException   When the identifier matches no CustomerApp (nothing persisted).
     * @throws InvalidFindingsPayloadException When the payload fails allow-list validation (nothing persisted).
     */
    public function ingest(string $appIdentifier, array $packages, array $findings): array
    {
        // Resolve the app first. If it does not exist, persist nothing (Req 3.3).
        $app = CustomerApp::query()->find($appIdentifier);

        if ($app === null) {
            throw UnknownCustomerAppException::forIdentifier($appIdentifier);
        }

        // Validate the whole payload against an explicit allow-list before
        // touching the database. On any failure, persist nothing (Req 3.4).
        $this->validate($packages, $findings);

        $now = now();

        $packageRows = array_map(static fn (array $package): array => [
            'customer_app_id' => $app->id,
            'name' => $package['name'],
            'version' => $package['version'],
            'synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_values($packages));

        $findingRows = array_map(static fn (array $finding): array => [
            'customer_app_id' => $app->id,
            'severity' => $finding['severity'],
            'description' => $finding['description'],
            'status' => $finding['status'],
            'synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_values($findings));

        // Bulk replace: delete existing child rows then insert the submitted
        // set, all in one transaction so a failure rolls back cleanly and a
        // repeat of the same payload is idempotent (Req 2.3, 2.5, 3.2).
        DB::transaction(function () use ($app, $packageRows, $findingRows): void {
            $app->packages()->delete();
            $app->findings()->delete();

            if ($packageRows !== []) {
                $app->packages()->getRelated()->newQuery()->insert($packageRows);
            }

            if ($findingRows !== []) {
                $app->findings()->getRelated()->newQuery()->insert($findingRows);
            }
        });

        return [
            'packages' => count($packageRows),
            'findings' => count($findingRows),
        ];
    }

    /**
     * Allow-list validation. Each package must carry a non-empty name and
     * version; each finding must carry a non-empty severity and description and
     * a status drawn from SecurityFinding::STATUSES. Collects every offending
     * field so the caller can report them all at once.
     *
     * @param array<int, array<string, mixed>> $packages
     * @param array<int, array<string, mixed>> $findings
     *
     * @throws InvalidFindingsPayloadException
     */
    private function validate(array $packages, array $findings): void
    {
        $errors = [];

        foreach (array_values($packages) as $index => $package) {
            if (! is_array($package)) {
                $errors[] = "packages[{$index}] must be an object.";
                continue;
            }

            if (! $this->filled($package, 'name')) {
                $errors[] = "packages[{$index}].name is required.";
            }

            if (! $this->filled($package, 'version')) {
                $errors[] = "packages[{$index}].version is required.";
            }
        }

        foreach (array_values($findings) as $index => $finding) {
            if (! is_array($finding)) {
                $errors[] = "findings[{$index}] must be an object.";
                continue;
            }

            if (! $this->filled($finding, 'severity')) {
                $errors[] = "findings[{$index}].severity is required.";
            }

            if (! $this->filled($finding, 'description')) {
                $errors[] = "findings[{$index}].description is required.";
            }

            if (! isset($finding['status']) || ! in_array($finding['status'], SecurityFinding::STATUSES, true)) {
                $allowed = implode(', ', SecurityFinding::STATUSES);
                $errors[] = "findings[{$index}].status must be one of: {$allowed}.";
            }
        }

        if ($errors !== []) {
            throw new InvalidFindingsPayloadException($errors);
        }
    }

    /**
     * A field is present when it exists and is a non-empty scalar after trimming.
     *
     * @param array<string, mixed> $row
     */
    private function filled(array $row, string $key): bool
    {
        if (! array_key_exists($key, $row)) {
            return false;
        }

        $value = $row[$key];

        if (! is_scalar($value)) {
            return false;
        }

        return trim((string) $value) !== '';
    }
}
