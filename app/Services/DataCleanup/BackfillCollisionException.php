<?php

namespace App\Services\DataCleanup;

use RuntimeException;

/** Thrown by ServiceDomainBackfill::apply() when canonical names collide. */
class BackfillCollisionException extends RuntimeException
{
    /** @param  list<array{table:string,canonical:string,row_ids:list<int|string>,values:list<string>}>  $collisions */
    public function __construct(public readonly array $collisions)
    {
        $parts = array_map(
            fn (array $c) => sprintf('%s "%s" (rows %s)', $c['table'], $c['canonical'], implode(', ', $c['row_ids'])),
            $collisions
        );
        parent::__construct('Backfill aborted, canonicalisation collisions: '.implode('; ', $parts));
    }
}
