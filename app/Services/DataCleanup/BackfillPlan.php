<?php

namespace App\Services\DataCleanup;

/**
 * Result of ServiceDomainBackfill::plan(): per-row changes plus findings.
 *
 * A change is an array with keys: table, key (primary key column), row_id,
 * column, old, new, action. Findings are reported but never changed.
 */
final class BackfillPlan
{
    public const ACTION_TRIM = 'trim';
    public const ACTION_EMPTY_TO_NULL = 'empty_to_null';
    public const ACTION_LOWERCASE = 'lowercase';
    public const ACTION_MAP_LABEL = 'map_label';
    public const ACTION_CANONICALISE = 'canonicalise';
    public const ACTION_LINK_DOMAIN = 'link_domain';
    public const ACTION_PROVISIONING_SUCCEEDED = 'provisioning_succeeded';
    public const ACTION_ENOM_DOMAIN_NAME_ID = 'enom_domain_name_id';

    /**
     * @param  list<array{table:string,key:string,row_id:int|string,column:string,old:?string,new:?string,action:string}>  $changes
     * @param  list<array{table:string,row_id:int|string,column:string,value:string}>  $unmatchedLabels
     * @param  list<array{table:string,row_id:int|string,column:string,value:string}>  $invalidDomains
     * @param  list<array{table:string,canonical:string,row_ids:list<int|string>,values:list<string>}>  $collisions
     */
    public function __construct(
        public readonly array $changes = [],
        public readonly array $unmatchedLabels = [],
        public readonly array $invalidDomains = [],
        public readonly array $collisions = [],
    ) {}

    /** @return array<string,int> number of changes per action */
    public function counts(): array
    {
        $counts = [];
        foreach ($this->changes as $change) {
            $counts[$change['action']] = ($counts[$change['action']] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    /** @return list<array<string,mixed>> */
    public function changesFor(string $table, ?string $column = null): array
    {
        return array_values(array_filter(
            $this->changes,
            fn (array $c) => $c['table'] === $table && ($column === null || $c['column'] === $column)
        ));
    }

    public function hasCollisions(): bool
    {
        return $this->collisions !== [];
    }

    public function isEmpty(): bool
    {
        return $this->changes === [];
    }
}
