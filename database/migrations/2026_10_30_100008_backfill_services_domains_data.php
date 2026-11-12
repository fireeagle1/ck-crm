<?php

use App\Models\ServiceDataCleanupChange;
use App\Services\DataCleanup\ServiceDomainBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M3: services/domains data backfill. Reversible via the service_data_cleanup_changes log.
 */
return new class extends Migration
{
    public function up(): void
    {
        $backfill = new ServiceDomainBackfill;
        $backfill->apply($backfill->plan());
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_data_cleanup_changes')) {
            return;
        }

        $keys = ['services' => 'service_id'];

        ServiceDataCleanupChange::query()
            ->where('migration', ServiceDomainBackfill::MIGRATION)
            ->orderByDesc('id')
            ->each(function ($log) use ($keys) {
                // Findings (collisions, unmatched labels, invalid domains) were logged unchanged.
                if ($log->old_value === $log->new_value) {
                    return;
                }
                $table = $log->table_name;
                $column = $log->column_name;
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                    return;
                }

                $query = DB::table($table)->where($keys[$table] ?? 'id', $log->row_id);
                $log->new_value === null ? $query->whereNull($column) : $query->where($column, $log->new_value);

                $old = $log->old_value;
                if ($column === 'domain_id' && $old !== null) {
                    $old = (int) $old;
                }
                $query->update([$column => $old]);
            });

        ServiceDataCleanupChange::query()->where('migration', ServiceDomainBackfill::MIGRATION)->delete();
    }
};
