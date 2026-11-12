<?php

namespace App\Services\DataCleanup;

use RuntimeException;

/** Thrown by UniqueIndexPreflight when duplicates would block a unique index. */
class DuplicateValuesException extends RuntimeException
{
    /** @param  list<array{table:string,column:string,value:string,ids:list<int|string>}>  $duplicates */
    public function __construct(public readonly array $duplicates)
    {
        $parts = array_map(
            fn (array $d) => sprintf('%s.%s "%s" (ids %s)', $d['table'], $d['column'], $d['value'], implode(', ', $d['ids'])),
            $duplicates
        );

        parent::__construct('Unique index preflight failed, duplicate values: '.implode('; ', $parts));
    }
}
