<?php

namespace App\Console\Commands;

use App\Models\ScheduledTaskLog;
use App\Services\DataCleanup\BackfillCollisionException;
use App\Services\DataCleanup\BackfillPlan;
use App\Services\DataCleanup\ServiceDomainBackfill;
use App\Services\DataCleanup\UniqueIndexPreflight;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormaliseServiceDataCommand extends Command
{
    protected $signature = 'services:normalise-data {--apply : Write the changes (default is a read-only dry run)}';

    protected $description = 'Dry-run (default) or apply the services/domains data normalisation backfill';

    public function handle(ServiceDomainBackfill $backfill, UniqueIndexPreflight $preflight): int
    {
        $apply = (bool) $this->option('apply');
        $log = ScheduledTaskLog::begin('services:normalise-data', ['mode' => $apply ? 'apply' : 'dry-run']);

        try {
            $plan = $backfill->plan();
            $wouldBeDuplicates = $this->wouldBeDuplicates($plan);
            $report = $this->report($plan, $preflight->findDuplicates(), $wouldBeDuplicates);

            if (! $apply) {
                $this->info('Dry run: nothing was written. Re-run with --apply to write the changes.');
                $log->complete($report['summary'], $report['meta']);

                return self::SUCCESS;
            }

            if ($wouldBeDuplicates !== []) {
                $this->error('Aborted: normalisation would create duplicates that block the unique indexes. No changes were written.');
                $log->fail('Duplicates after normalisation: '.count($wouldBeDuplicates), $report['meta']);

                return self::FAILURE;
            }

            try {
                $backfill->apply($plan);
            } catch (BackfillCollisionException $e) {
                $this->error('Aborted: canonicalisation collisions must be resolved first. No changes were written.');
                $log->fail('Canonicalisation collisions: '.count($plan->collisions), $report['meta']);

                return self::FAILURE;
            }

            $this->info('Applied '.count($plan->changes).' change(s).');
            $log->complete('Applied '.count($plan->changes).' change(s). '.$report['summary'], $report['meta']);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $log->fail($e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Duplicates that would exist in services.cpanel_username / domain_name once the plan is applied.
     *
     * @return list<array{column:string,value:string,ids:list<int|string>}>
     */
    private function wouldBeDuplicates(BackfillPlan $plan): array
    {
        $final = [];
        foreach (UniqueIndexPreflight::COLUMNS as $column) {
            foreach (DB::table('services')->whereNotNull($column)->pluck($column, 'service_id') as $id => $value) {
                $final[$column][$id] = $value;
            }
        }
        foreach ($plan->changesFor('services') as $c) {
            if (in_array($c['column'], UniqueIndexPreflight::COLUMNS, true)) {
                $final[$c['column']][$c['row_id']] = $c['new'];
            }
        }

        $out = [];
        foreach ($final as $column => $values) {
            $groups = [];
            foreach ($values as $id => $value) {
                if ($value !== null) {
                    $groups[$value][] = $id;
                }
            }
            foreach ($groups as $value => $ids) {
                if (count($ids) > 1) {
                    $out[] = ['column' => $column, 'value' => (string) $value, 'ids' => $ids];
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string,mixed>>  $currentDuplicates
     * @param  list<array<string,mixed>>  $wouldBeDuplicates
     * @return array{summary:string,meta:array<string,mixed>}
     */
    private function report(BackfillPlan $plan, array $currentDuplicates, array $wouldBeDuplicates): array
    {
        $counts = $plan->counts();
        $emptyConversions = $counts[BackfillPlan::ACTION_EMPTY_TO_NULL] ?? 0;

        $this->line('Planned changes: '.count($plan->changes));
        foreach ($counts as $action => $n) {
            $this->line("  {$action}: {$n}");
        }
        $this->line("Empty-string conversions (to NULL): {$emptyConversions}");

        $this->section('Unmatched labels', $plan->unmatchedLabels,
            fn ($f) => "{$f['table']}#{$f['row_id']} {$f['column']} = '{$f['value']}'");
        $this->section('Invalid domain names (left unchanged)', $plan->invalidDomains,
            fn ($f) => "{$f['table']}#{$f['row_id']} {$f['column']} = '{$f['value']}'");
        $this->section('Existing duplicates (unique index preflight)', $currentDuplicates,
            fn ($d) => "services.{$d['column']} = '{$d['value']}' ids: ".implode(',', $d['ids']));
        $this->section('Duplicates after normalisation', $wouldBeDuplicates,
            fn ($d) => "services.{$d['column']} = '{$d['value']}' ids: ".implode(',', $d['ids']));
        $this->section('Canonicalisation collisions', $plan->collisions,
            fn ($c) => "{$c['table']} '{$c['canonical']}' rows: ".implode(',', $c['row_ids']));

        $meta = [
            'counts' => $counts,
            'empty_string_conversions' => $emptyConversions,
            'unmatched_labels' => count($plan->unmatchedLabels),
            'invalid_domains' => count($plan->invalidDomains),
            'existing_duplicates' => count($currentDuplicates),
            'would_be_duplicates' => count($wouldBeDuplicates),
            'collisions' => count($plan->collisions),
        ];

        return [
            'summary' => sprintf(
                '%d change(s); %d unmatched label(s); %d duplicate(s); %d collision(s).',
                count($plan->changes), count($plan->unmatchedLabels),
                count($currentDuplicates) + count($wouldBeDuplicates), count($plan->collisions)
            ),
            'meta' => $meta,
        ];
    }

    /** @param  list<array<string,mixed>>  $items */
    private function section(string $title, array $items, callable $format): void
    {
        $this->line("{$title}: ".count($items));
        foreach ($items as $item) {
            $this->line('  - '.$format($item));
        }
    }
}
