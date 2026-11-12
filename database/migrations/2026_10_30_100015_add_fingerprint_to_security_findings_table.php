<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * M9: add security_findings.fingerprint and last_seen_at, backfill fingerprints,
 * merge duplicates per app, then add unique(customer_app_id, fingerprint).
 */
return new class extends Migration
{
    private const INDEX = 'security_findings_customer_app_id_fingerprint_unique';

    public static function fingerprint(string $description): string
    {
        $normalised = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', trim($description))));

        return hash('sha256', $normalised);
    }

    public function up(): void
    {
        if (! Schema::hasColumn('security_findings', 'fingerprint')) {
            Schema::table('security_findings', function (Blueprint $table) {
                $table->char('fingerprint', 64)->nullable();
            });
        }

        if (! Schema::hasColumn('security_findings', 'last_seen_at')) {
            Schema::table('security_findings', function (Blueprint $table) {
                $table->timestamp('last_seen_at')->nullable();
            });
        }

        $this->backfill();
        $this->deduplicate();

        if (! Schema::hasIndex('security_findings', self::INDEX)) {
            Schema::table('security_findings', function (Blueprint $table) {
                $table->unique(['customer_app_id', 'fingerprint'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('security_findings', self::INDEX)) {
            Schema::table('security_findings', function (Blueprint $table) {
                $table->dropUnique(self::INDEX);
            });
        }

        foreach (['fingerprint', 'last_seen_at'] as $column) {
            if (Schema::hasColumn('security_findings', $column)) {
                Schema::table('security_findings', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function backfill(): void
    {
        DB::table('security_findings')
            ->whereNull('fingerprint')
            ->orderBy('id')
            ->select(['id', 'description'])
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('security_findings')
                        ->where('id', $row->id)
                        ->update(['fingerprint' => self::fingerprint((string) $row->description)]);
                }
            });
    }

    private function deduplicate(): void
    {
        $groups = DB::table('security_findings')
            ->select('customer_app_id', 'fingerprint')
            ->whereNotNull('fingerprint')
            ->groupBy('customer_app_id', 'fingerprint')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $rows = DB::table('security_findings')
                ->where('customer_app_id', $group->customer_app_id)
                ->where('fingerprint', $group->fingerprint)
                ->get(['id', 'status', 'updated_at'])
                ->sortByDesc(fn ($r) => [(string) $r->updated_at, $r->id])
                ->values();

            $decided = $rows->first(fn ($r) => in_array($r->status, ['resolved', 'dismissed'], true));
            $keep = $decided ?? $rows->first();
            $merged = $rows->where('id', '!=', $keep->id)->pluck('id')->all();

            DB::table('security_findings')->whereIn('id', $merged)->delete();

            Log::info('security_findings M9: merged duplicate findings', [
                'customer_app_id' => $group->customer_app_id,
                'fingerprint' => $group->fingerprint,
                'kept_id' => $keep->id,
                'merged_ids' => $merged,
            ]);
        }
    }
};
