<?php

namespace App\Services\DataCleanup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Checks that services.cpanel_username and services.domain_name hold no duplicate
 * non-NULL values before unique indexes are created (migration M4).
 */
class UniqueIndexPreflight
{
    public const COLUMNS = ['cpanel_username', 'domain_name'];

    /**
     * @return list<array{table:string,column:string,value:string,ids:list<int|string>}>
     */
    public function findDuplicates(): array
    {
        $duplicates = [];

        foreach (self::COLUMNS as $column) {
            $values = DB::table('services')
                ->select($column)
                ->whereNotNull($column)
                ->groupBy($column)
                ->havingRaw('COUNT(*) > 1')
                ->pluck($column);

            foreach ($values as $value) {
                $duplicates[] = [
                    'table' => 'services',
                    'column' => $column,
                    'value' => (string) $value,
                    'ids' => DB::table('services')->where($column, $value)->orderBy('service_id')
                        ->pluck('service_id')->all(),
                ];
            }
        }

        return $duplicates;
    }

    /** @throws DuplicateValuesException */
    public function assertNoDuplicates(): void
    {
        $duplicates = $this->findDuplicates();

        if ($duplicates === []) {
            return;
        }

        foreach ($duplicates as $duplicate) {
            Log::error('Unique index preflight failed: duplicate value', $duplicate);
        }

        throw new DuplicateValuesException($duplicates);
    }
}
