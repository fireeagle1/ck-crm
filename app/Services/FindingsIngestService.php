<?php

namespace App\Services;

use App\Exceptions\InvalidFindingsPayloadException;
use App\Exceptions\UnknownCustomerAppException;
use App\Models\CustomerApp;
use App\Models\SecurityFinding;
use Illuminate\Support\Facades\DB;

/**
 * Receives a payload of App_Packages and Security_Findings for one identified
 * app in a single transaction: packages are replaced, findings are upserted by
 * fingerprint and never deleted.
 *
 * This is the storage half of the findings receiving stub. It contains NO
 * GitHub, ChatGPT, or OpenAI logic — the future daily sync runs its scan
 * elsewhere and delivers its results here (Req 3.6).
 *
 * Identifier resolution: by CustomerApp `public_id` only. Numeric primary keys
 * are never accepted.
 */
class FindingsIngestService
{
    public const MAX_PAYLOAD_BYTES = 1048576; // 1 MB of JSON
    public const MAX_FINDINGS = 1000;

    /**
     * Validate, resolve and bulk-replace an app's packages and findings.
     *
     * @param string $appIdentifier The CustomerApp `public_id` (lowercase ULID; matched case-insensitively).
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
        // Resolve by public_id only (lowercase ULID). Numeric primary keys and
        // any other unknown value do not match. Nothing persisted (Req 17.1, 17.5).
        $app = CustomerApp::query()
            ->where('public_id', strtolower(trim($appIdentifier)))
            ->first();

        if ($app === null) {
            throw UnknownCustomerAppException::forIdentifier($appIdentifier);
        }

        // Payload limits, enforced before any write (Req 17.6).
        $this->enforceLimits($packages, $findings);

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

        // Collapse duplicates within the payload by fingerprint; the last wins.
        $payloadFindings = [];
        foreach (array_values($findings) as $finding) {
            $payloadFindings[SecurityFinding::fingerprintFor((string) $finding['description'])] = $finding;
        }

        // Packages are replaced; findings are upserted by fingerprint and never
        // deleted, all in one transaction (Req 17.2, 17.3, 17.4, 17.7).
        DB::transaction(function () use ($app, $packageRows, $payloadFindings, $now): void {
            $app->packages()->delete();

            if ($packageRows !== []) {
                $app->packages()->getRelated()->newQuery()->insert($packageRows);
            }

            $this->upsertFindings($app, $payloadFindings, $now);
        });

        $findingRows = $payloadFindings;

        return [
            'packages' => count($packageRows),
            'findings' => count($findingRows),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $payloadFindings Keyed by fingerprint.
     */
    private function upsertFindings(CustomerApp $app, array $payloadFindings, \DateTimeInterface $now): void
    {
        $stored = SecurityFinding::query()
            ->where('customer_app_id', $app->id)
            ->get()
            ->keyBy(fn (SecurityFinding $f): string => $f->fingerprint ?? SecurityFinding::fingerprintFor((string) $f->description));

        foreach ($payloadFindings as $fingerprint => $finding) {
            $existing = $stored->get($fingerprint);

            if ($existing === null) {
                SecurityFinding::create([
                    'customer_app_id' => $app->id,
                    'fingerprint' => $fingerprint,
                    'severity' => $finding['severity'],
                    'description' => $finding['description'],
                    'status' => $finding['status'],
                    'synced_at' => $now,
                    'last_seen_at' => $now,
                ]);

                continue;
            }

            $attributes = [
                'fingerprint' => $fingerprint,
                'severity' => $finding['severity'],
                'description' => $finding['description'],
                'synced_at' => $now,
                'last_seen_at' => $now,
            ];

            // Resolved and dismissed decisions are never reset from the payload.
            if (in_array($existing->status, [SecurityFinding::STATUS_OPEN, SecurityFinding::STATUS_NOT_REPORTED], true)) {
                $attributes['status'] = $finding['status'];
            }

            $existing->update($attributes);
        }

        // Stored open findings absent from the payload become not_reported.
        foreach ($stored as $fingerprint => $existing) {
            if (! isset($payloadFindings[$fingerprint]) && $existing->status === SecurityFinding::STATUS_OPEN) {
                $existing->update(['status' => SecurityFinding::STATUS_NOT_REPORTED]);
            }
        }
    }

    /**
     * @param array<int, mixed> $packages
     * @param array<int, mixed> $findings
     *
     * @throws InvalidFindingsPayloadException
     */
    private function enforceLimits(array $packages, array $findings): void
    {
        if (count($findings) > self::MAX_FINDINGS) {
            throw new InvalidFindingsPayloadException([
                sprintf('findings may contain at most %d entries.', self::MAX_FINDINGS),
            ]);
        }

        $json = json_encode(['packages' => $packages, 'findings' => $findings], JSON_PARTIAL_OUTPUT_ON_ERROR);

        if ($json === false || strlen($json) > self::MAX_PAYLOAD_BYTES) {
            throw new InvalidFindingsPayloadException([
                sprintf('payload may be at most %d bytes of JSON.', self::MAX_PAYLOAD_BYTES),
            ]);
        }
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

            if (! isset($finding['status']) || ! in_array($finding['status'], SecurityFinding::PAYLOAD_STATUSES, true)) {
                $allowed = implode(', ', SecurityFinding::PAYLOAD_STATUSES);
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
