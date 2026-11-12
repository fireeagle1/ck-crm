<?php

namespace App\Services\DataCleanup;

use App\Enums\ServiceStatus;
use App\Enums\ServiceType;
use App\Models\ServiceDataCleanupChange;
use App\Support\DomainNormaliser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Plans the services/domains data backfill. plan() is strictly read-only and only
 * selects columns that exist before the cleanup migrations, so it can run against
 * production ahead of the deploy. apply() (task 2.13) consumes the plan.
 */
class ServiceDomainBackfill
{
    private const CHUNK = 500;

    private const APPLY_CHUNK = 200;

    public const MIGRATION = 'm3';

    /** Log tag for the DomainNameID backfill (migration M7b). */
    public const MIGRATION_ENOM = 'm7b';

    private const ENOM_ID_MAX_LENGTH = 32;

    /** @var list<array<string,mixed>> */
    private array $changes = [];

    /** @var list<array<string,mixed>> */
    private array $unmatchedLabels = [];

    /** @var list<array<string,mixed>> */
    private array $invalidDomains = [];

    /** @var list<array<string,mixed>> */
    private array $collisions = [];

    public function plan(): BackfillPlan
    {
        $this->changes = $this->unmatchedLabels = $this->invalidDomains = $this->collisions = [];

        $domainIds = $this->planDomains();
        $this->planEnomDomainNameIds();
        $this->planServices($domainIds);
        $this->planOrderItems($domainIds);

        return new BackfillPlan($this->changes, $this->unmatchedLabels, $this->invalidDomains, $this->collisions);
    }

    /**
     * Apply a plan in chunks of 200 inside transactions, logging every change.
     *
     * Each update is guarded by the planned old value, so re-running the same plan
     * (or a fresh plan after a run) changes and logs nothing. Unmatched labels and
     * invalid domains are left untouched and logged once (old = new = value).
     * Canonicalisation collisions are logged and abort the run before any write.
     *
     * @throws BackfillCollisionException
     */
    public function apply(BackfillPlan $plan): void
    {
        if ($plan->hasCollisions()) {
            $this->logCollisions($plan);
            throw new BackfillCollisionException($plan->collisions);
        }

        foreach (array_chunk($plan->changes, self::APPLY_CHUNK) as $chunk) {
            DB::transaction(function () use ($chunk) {
                foreach ($chunk as $change) {
                    $this->applyChange($change);
                }
            });
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan->unmatchedLabels as $f) {
                $this->logFindingOnce($f);
            }
            foreach ($plan->invalidDomains as $f) {
                $this->logFindingOnce($f);
            }
        });
    }

    /** @param  array<string,mixed>  $change */
    private function applyChange(array $change): void
    {
        $table = $change['table'];
        $column = $change['column'];
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        $query = DB::table($table)->where($change['key'], $change['row_id']);
        $change['old'] === null ? $query->whereNull($column) : $query->where($column, $change['old']);

        $new = $change['new'];
        if ($column === 'domain_id' && $new !== null) {
            $new = (int) $new;
        }

        if ($query->update([$column => $new]) > 0) {
            $migration = $column === 'enom_domain_name_id' ? self::MIGRATION_ENOM : self::MIGRATION;
            ServiceDataCleanupChange::record($migration, $table, $change['row_id'], $column, $change['old'], $change['new']);
        }
    }

    /** @param  array{table:string,row_id:int|string,column:string,value:string}  $finding */
    private function logFindingOnce(array $finding): void
    {
        $this->logUnchanged($finding['table'], $finding['row_id'], $finding['column'], $finding['value']);
    }

    private function logUnchanged(string $table, int|string $rowId, string $column, string $value): void
    {
        $exists = ServiceDataCleanupChange::query()
            ->where('migration', self::MIGRATION)
            ->where('table_name', $table)
            ->where('row_id', (string) $rowId)
            ->where('column_name', $column)
            ->where('old_value', $value)
            ->where('new_value', $value)
            ->exists();

        if (! $exists) {
            ServiceDataCleanupChange::record(self::MIGRATION, $table, $rowId, $column, $value, $value);
        }
    }

    private function logCollisions(BackfillPlan $plan): void
    {
        foreach ($plan->collisions as $collision) {
            Log::error('Data cleanup aborted: canonicalisation collision', $collision);
            foreach ($collision['row_ids'] as $i => $rowId) {
                $this->logUnchanged($collision['table'], $rowId, 'domain_name', $collision['values'][$i]);
            }
        }
    }

    /**
     * Canonicalise domains.domain_name and return canonical name => domain id for
     * every domain whose name is unambiguous.
     *
     * @return array<string,int>
     */
    private function planDomains(): array
    {
        if (! Schema::hasTable('domains')) {
            return [];
        }

        $rows = [];
        DB::table('domains')->select(['id', 'domain_name'])->chunkById(self::CHUNK, function ($chunk) use (&$rows) {
            foreach ($chunk as $row) {
                $rows[] = ['id' => $row->id, 'orig' => $row->domain_name];
            }
        }, 'id');

        $finals = [];
        foreach ($rows as $i => $r) {
            $clean = $this->clean($r['orig']);
            $final = $r['orig'];
            if ($clean === null) {
                $this->invalidDomains[] = $this->finding('domains', $r['id'], 'domain_name', (string) $r['orig']);
            } else {
                $canonical = DomainNormaliser::tryNormalise($clean);
                if ($canonical === null) {
                    $this->invalidDomains[] = $this->finding('domains', $r['id'], 'domain_name', $clean);
                } else {
                    $final = $canonical;
                }
            }
            $finals[$i] = $final;
        }

        $blocked = $this->detectCollisions('domains', $rows, $finals);

        $map = [];
        foreach ($rows as $i => $r) {
            if (isset($blocked[$i])) {
                continue;
            }
            $final = $finals[$i];
            if ($final !== $r['orig'] && $final !== null) {
                $this->addChange('domains', 'id', $r['id'], 'domain_name', $r['orig'], $final, BackfillPlan::ACTION_CANONICALISE);
            }
            if ($final !== null && DomainNormaliser::tryNormalise($final) === $final) {
                $map[$final] = (int) $r['id'];
            }
        }

        return $map;
    }

    /**
     * Copy DomainNameID from the enom_response JSON text into enom_domain_name_id.
     * Only when both columns exist and the target is currently null; malformed,
     * empty or missing JSON / DomainNameID values are ignored.
     */
    private function planEnomDomainNameIds(): void
    {
        if (
            ! Schema::hasTable('domains')
            || ! Schema::hasColumn('domains', 'enom_response')
            || ! Schema::hasColumn('domains', 'enom_domain_name_id')
        ) {
            return;
        }

        DB::table('domains')
            ->select(['id', 'enom_response'])
            ->whereNull('enom_domain_name_id')
            ->whereNotNull('enom_response')
            ->chunkById(self::CHUNK, function ($chunk) {
                foreach ($chunk as $row) {
                    $id = $this->extractDomainNameId($row->enom_response);
                    if ($id !== null) {
                        $this->addChange('domains', 'id', $row->id, 'enom_domain_name_id', null, $id, BackfillPlan::ACTION_ENOM_DOMAIN_NAME_ID);
                    }
                }
            }, 'id');
    }

    private function extractDomainNameId(?string $json): ?string
    {
        if ($json === null || trim($json) === '') {
            return null;
        }
        $data = json_decode($json, true);
        if (! is_array($data) || ! array_key_exists('DomainNameID', $data)) {
            return null;
        }
        $value = $data['DomainNameID'];
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' || mb_strlen($value) > self::ENOM_ID_MAX_LENGTH ? null : $value;
    }

    /** @param  array<string,int>  $domainIds */
    private function planServices(array $domainIds): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        $columns = ['service_id'];
        foreach (['service_type', 'status', 'domain_name', 'cpanel_username', 'domain_id', 'provisioning_status'] as $col) {
            if (Schema::hasColumn('services', $col)) {
                $columns[] = $col;
            }
        }

        $rows = [];
        DB::table('services')->select($columns)->chunkById(self::CHUNK, function ($chunk) use (&$rows) {
            foreach ($chunk as $row) {
                $rows[] = (array) $row;
            }
        }, 'service_id');

        // First pass: final domain_name per row, so collisions can be found.
        $domainState = [];
        $collisionRows = [];
        foreach ($rows as $i => $r) {
            $domainState[$i] = $this->resolveDomain('services', $r['service_id'], 'domain_name', $r['domain_name'] ?? null);
            $collisionRows[$i] = ['id' => $r['service_id'], 'orig' => $r['domain_name'] ?? null];
        }
        $finals = array_map(fn ($s) => $s['final'], $domainState);
        $blocked = $this->detectCollisions('services', $collisionRows, $finals);

        foreach ($rows as $i => $r) {
            $id = $r['service_id'];

            // cpanel_username: trim, '' => NULL, lowercase.
            $finalCpanel = $r['cpanel_username'] ?? null;
            if (array_key_exists('cpanel_username', $r)) {
                $clean = $this->clean($r['cpanel_username']);
                $finalCpanel = $clean === null ? null : mb_strtolower($clean);
                $this->addTextChange('services', 'service_id', $id, 'cpanel_username', $r['cpanel_username'], $finalCpanel);
            }

            // domain_name
            $finalDomain = $r['domain_name'] ?? null;
            if (array_key_exists('domain_name', $r)) {
                if (isset($blocked[$i])) {
                    $finalDomain = $r['domain_name'];
                } else {
                    $state = $domainState[$i];
                    $finalDomain = $state['final'];
                    $this->addDomainChange('services', 'service_id', $id, $r['domain_name'], $state);
                }
            }

            // Labels
            $finalType = $r['service_type'] ?? null;
            foreach (['service_type' => ServiceType::class, 'status' => ServiceStatus::class] as $col => $enum) {
                if (! array_key_exists($col, $r) || $r[$col] === null) {
                    continue;
                }
                $mapped = $enum::fromLoose($r[$col]);
                if ($mapped === null) {
                    $this->unmatchedLabels[] = $this->finding('services', $id, $col, (string) $r[$col]);

                    continue;
                }
                if ($col === 'service_type') {
                    $finalType = $mapped->value;
                }
                if ($mapped->value !== $r[$col]) {
                    $this->addChange('services', 'service_id', $id, $col, $r[$col], $mapped->value, BackfillPlan::ACTION_MAP_LABEL);
                }
            }

            // provisioning_status for Web Hosting services already on cPanel.
            if (
                Schema::hasColumn('services', 'provisioning_status')
                && $finalType === ServiceType::WebHosting->value
                && $finalCpanel !== null
                && ($r['provisioning_status'] ?? null) === null
            ) {
                $this->addChange('services', 'service_id', $id, 'provisioning_status', null, 'succeeded', BackfillPlan::ACTION_PROVISIONING_SUCCEEDED);
            }

            // domain_id
            if (! isset($blocked[$i])) {
                $this->planLink('services', 'service_id', $id, $domainState[$i]['final'] ?? null, $domainState[$i]['valid'] ?? false, $r['domain_id'] ?? null, $domainIds);
            }
        }
    }

    /** @param  array<string,int>  $domainIds */
    private function planOrderItems(array $domainIds): void
    {
        if (! Schema::hasTable('order_items') || ! Schema::hasColumn('order_items', 'domain_name')) {
            return;
        }

        $columns = ['id', 'domain_name'];
        if (Schema::hasColumn('order_items', 'domain_id')) {
            $columns[] = 'domain_id';
        }

        DB::table('order_items')->select($columns)->chunkById(self::CHUNK, function ($chunk) use ($domainIds) {
            foreach ($chunk as $row) {
                $state = $this->resolveDomain('order_items', $row->id, 'domain_name', $row->domain_name);
                $this->addDomainChange('order_items', 'id', $row->id, $row->domain_name, $state);
                $this->planLink('order_items', 'id', $row->id, $state['final'], $state['valid'], $row->domain_id ?? null, $domainIds);
            }
        }, 'id');
    }

    /**
     * Trim, convert '' to NULL, then canonicalise. Invalid values are reported and kept (trimmed).
     *
     * @return array{final:?string,valid:bool,canonicalised:bool}
     */
    private function resolveDomain(string $table, int|string $id, string $column, ?string $orig): array
    {
        $clean = $this->clean($orig);
        if ($clean === null) {
            return ['final' => null, 'valid' => false, 'canonicalised' => false];
        }
        $canonical = DomainNormaliser::tryNormalise($clean);
        if ($canonical === null) {
            $this->invalidDomains[] = $this->finding($table, $id, $column, $clean);

            return ['final' => $clean, 'valid' => false, 'canonicalised' => false];
        }

        return ['final' => $canonical, 'valid' => true, 'canonicalised' => $canonical !== $clean];
    }

    /** @param  array{final:?string,valid:bool,canonicalised:bool}  $state */
    private function addDomainChange(string $table, string $key, int|string $id, ?string $orig, array $state): void
    {
        $final = $state['final'];
        if ($final === $orig) {
            return;
        }
        if ($final === null) {
            $action = BackfillPlan::ACTION_EMPTY_TO_NULL;
        } elseif ($state['canonicalised']) {
            $action = BackfillPlan::ACTION_CANONICALISE;
        } else {
            $action = BackfillPlan::ACTION_TRIM;
        }
        $this->addChange($table, $key, $id, 'domain_name', $orig, $final, $action);
    }

    /** @param  array<string,int>  $domainIds */
    private function planLink(string $table, string $key, int|string $id, ?string $final, bool $valid, mixed $current, array $domainIds): void
    {
        if (! $valid || $final === null || ! isset($domainIds[$final])) {
            return;
        }
        $domainId = $domainIds[$final];
        if ($current !== null && (int) $current === $domainId) {
            return;
        }
        $this->addChange($table, $key, $id, 'domain_id', $current === null ? null : (string) $current, (string) $domainId, BackfillPlan::ACTION_LINK_DOMAIN);
    }

    /**
     * Rows whose canonical names collide and where at least one name changes.
     *
     * @param  list<array{id:int|string,orig:?string}>  $rows
     * @param  array<int,?string>  $finals
     * @return array<int,true> indexes of rows in a collision group
     */
    private function detectCollisions(string $table, array $rows, array $finals): array
    {
        $groups = [];
        foreach ($rows as $i => $r) {
            if ($finals[$i] !== null) {
                $groups[$finals[$i]][] = $i;
            }
        }

        $blocked = [];
        foreach ($groups as $canonical => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }
            $changed = false;
            foreach ($indexes as $i) {
                $changed = $changed || $rows[$i]['orig'] !== $finals[$i];
            }
            if (! $changed) {
                continue;
            }
            $this->collisions[] = [
                'table' => $table,
                'canonical' => (string) $canonical,
                'row_ids' => array_map(fn ($i) => $rows[$i]['id'], $indexes),
                'values' => array_map(fn ($i) => (string) $rows[$i]['orig'], $indexes),
            ];
            foreach ($indexes as $i) {
                $blocked[$i] = true;
            }
        }

        return $blocked;
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = preg_replace('/^\s+|\s+$/u', '', $value) ?? trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function addTextChange(string $table, string $key, int|string $id, string $column, ?string $orig, ?string $final): void
    {
        if ($orig === $final) {
            return;
        }
        if ($final === null) {
            $action = BackfillPlan::ACTION_EMPTY_TO_NULL;
        } elseif (mb_strtolower($final) === $final && trim($orig ?? '') !== $final) {
            $action = BackfillPlan::ACTION_LOWERCASE;
        } else {
            $action = BackfillPlan::ACTION_TRIM;
        }
        $this->addChange($table, $key, $id, $column, $orig, $final, $action);
    }

    private function addChange(string $table, string $key, int|string $id, string $column, ?string $old, ?string $new, string $action): void
    {
        $this->changes[] = [
            'table' => $table,
            'key' => $key,
            'row_id' => $id,
            'column' => $column,
            'old' => $old,
            'new' => $new,
            'action' => $action,
        ];
    }

    /** @return array{table:string,row_id:int|string,column:string,value:string} */
    private function finding(string $table, int|string $id, string $column, string $value): array
    {
        return ['table' => $table, 'row_id' => $id, 'column' => $column, 'value' => $value];
    }
}
