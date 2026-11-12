<?php

use App\Models\ServiceDataCleanupChange;
use App\Services\DataCleanup\DomainLinkPreflight;
use App\Services\DataCleanup\ServiceDomainBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M7b: backfill domains.enom_domain_name_id from enom_response, then add
 * unique(services.domain_id). The preflight throws (logging every duplicate)
 * before the index is created.
 */
return new class extends Migration
{
    public function up(): void
    {
        $backfill = new ServiceDomainBackfill;
        $backfill->apply($backfill->plan());

        (new DomainLinkPreflight)->assertNoDuplicates();

        if (! Schema::hasIndex('services', ['domain_id'], 'unique')) {
            Schema::table('services', function (Blueprint $table) {
                $table->unique('domain_id', 'services_domain_id_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('services', 'services_domain_id_unique')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropUnique('services_domain_id_unique');
            });
        }

        if (
            ! Schema::hasTable('service_data_cleanup_changes')
            || ! Schema::hasColumn('domains', 'enom_domain_name_id')
        ) {
            return;
        }

        ServiceDataCleanupChange::query()
            ->where('migration', ServiceDomainBackfill::MIGRATION_ENOM)
            ->orderByDesc('id')
            ->each(function ($log) {
                if ($log->old_value === $log->new_value) {
                    return;
                }
                $query = DB::table('domains')->where('id', $log->row_id);
                $log->new_value === null
                    ? $query->whereNull('enom_domain_name_id')
                    : $query->where('enom_domain_name_id', $log->new_value);
                $query->update(['enom_domain_name_id' => $log->old_value]);
            });

        ServiceDataCleanupChange::query()->where('migration', ServiceDomainBackfill::MIGRATION_ENOM)->delete();
    }
};
