<?php

namespace App\Services\DataCleanup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Checks that services.domain_id holds no duplicate non-NULL values before the
 * unique index is created (migration M7b).
 */
class DomainLinkPreflight
{
    /**
     * @return list<array{table:string,column:string,value:string,ids:list<int|string>}>
     */
    public function findDuplicates(): array
    {
        $duplicates = [];

        $values = DB::table('services')
            ->select('domain_id')
            ->whereNotNull('domain_id')
            ->groupBy('domain_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('domain_id');

        foreach ($values as $value) {
            $duplicates[] = [
                'table' => 'services',
                'column' => 'domain_id',
                'value' => (string) $value,
                'ids' => DB::table('services')->where('domain_id', $value)->orderBy('service_id')
                    ->pluck('service_id')->all(),
            ];
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
