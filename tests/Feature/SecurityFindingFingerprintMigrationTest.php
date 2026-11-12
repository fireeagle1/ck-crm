<?php

namespace Tests\Feature;

use App\Models\CustomerApp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * M9: security_findings fingerprint backfill, duplicate merge, unique index.
 *
 * _Requirements: 17.2, 17.3_
 */
class SecurityFindingFingerprintMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_30_100015_add_fingerprint_to_security_findings_table.php');
    }

    /** Roll M9 back so legacy (pre-fingerprint) rows can be inserted. */
    private function rollBack(): Migration
    {
        $migration = $this->migration();
        $migration->down();

        return $migration;
    }

    private function finding(int $appId, string $description, string $status = 'open', string $updatedAt = '2026-01-01 00:00:00'): int
    {
        return DB::table('security_findings')->insertGetId([
            'customer_app_id' => $appId,
            'severity' => 'high',
            'description' => $description,
            'status' => $status,
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);
    }

    public function test_schema_after_migrations(): void
    {
        $this->assertTrue(Schema::hasColumns('security_findings', ['fingerprint', 'last_seen_at']));
        $this->assertTrue(Schema::hasIndex('security_findings', ['customer_app_id', 'fingerprint'], 'unique'));
    }

    public function test_backfills_fingerprint_from_normalised_description(): void
    {
        $app = CustomerApp::factory()->create()->id;
        $migration = $this->rollBack();
        $id = $this->finding($app, "  Outdated   PHP\n version ");

        $migration->up();

        $this->assertSame(
            hash('sha256', 'outdated php version'),
            DB::table('security_findings')->where('id', $id)->value('fingerprint')
        );
    }

    public function test_merges_duplicates_keeping_decided_row_and_logging_ids(): void
    {
        $app = CustomerApp::factory()->create()->id;
        $other = CustomerApp::factory()->create()->id;
        $migration = $this->rollBack();

        $oldDismissed = $this->finding($app, 'XSS in form', 'dismissed', '2026-01-01 00:00:00');
        $newerOpen = $this->finding($app, 'xss  in FORM', 'open', '2026-02-01 00:00:00');
        $newest1 = $this->finding($app, 'SQLi', 'open', '2026-01-01 00:00:00');
        $newest2 = $this->finding($app, 'sqli', 'open', '2026-03-01 00:00:00');
        $otherApp = $this->finding($other, 'XSS in form');

        Log::spy();
        $migration->up();

        $ids = DB::table('security_findings')->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$oldDismissed, $newest2, $otherApp], $ids);
        $this->assertNotContains($newerOpen, $ids);
        $this->assertNotContains($newest1, $ids);

        Log::shouldHaveReceived('info')->withArgs(
            fn ($msg, $ctx = []) => ($ctx['merged_ids'] ?? null) === [$newerOpen] && $ctx['kept_id'] === $oldDismissed
        )->once();
        $this->assertTrue(Schema::hasIndex('security_findings', ['customer_app_id', 'fingerprint'], 'unique'));
    }

    public function test_up_is_idempotent(): void
    {
        $app = CustomerApp::factory()->create()->id;
        $migration = $this->rollBack();
        $this->finding($app, 'Weak password policy');
        $this->finding($app, 'weak password policy');

        $migration->up();
        $snapshot = DB::table('security_findings')->get()->toArray();
        $migration->up();

        $this->assertCount(1, $snapshot);
        $this->assertEquals($snapshot, DB::table('security_findings')->get()->toArray());
    }

    public function test_down_removes_columns_and_index(): void
    {
        $this->rollBack();

        $this->assertFalse(Schema::hasColumn('security_findings', 'fingerprint'));
        $this->assertFalse(Schema::hasColumn('security_findings', 'last_seen_at'));
    }
}
