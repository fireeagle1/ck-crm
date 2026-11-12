<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\DataCleanup\DuplicateValuesException;
use App\Services\DataCleanup\UniqueIndexPreflight;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * _Requirements: 2.1, 2.2, 2.4_
 */
class ServicesUniqueIndexesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private int|string|null $companyId = null;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_30_100009_add_unique_indexes_to_services_table.php');
    }

    private function dropIndexes(): void
    {
        $this->migration()->down();
    }

    private function insert(array $attrs): int
    {
        $base = [
            'company_id' => $this->companyId ??= Customer::factory()->create()->getKey(),
            'service_short' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return DB::table('services')->insertGetId($attrs + $base, 'service_id');
    }

    public function test_indexes_exist_after_migrations(): void
    {
        $this->assertTrue(Schema::hasIndex('services', ['cpanel_username'], 'unique'));
        $this->assertTrue(Schema::hasIndex('services', ['domain_name'], 'unique'));
    }

    public function test_up_is_idempotent_and_down_drops_indexes(): void
    {
        $m = $this->migration();
        $m->up();
        $this->assertTrue(Schema::hasIndex('services', ['domain_name'], 'unique'));

        $m->down();
        $this->assertFalse(Schema::hasIndex('services', ['cpanel_username'], 'unique'));
        $this->assertFalse(Schema::hasIndex('services', ['domain_name'], 'unique'));

        $m->up();
        $this->assertTrue(Schema::hasIndex('services', ['cpanel_username'], 'unique'));
    }

    public function test_duplicates_abort_with_log_and_create_no_index(): void
    {
        $this->dropIndexes();
        $a = $this->insert(['domain_name' => 'dup.com', 'cpanel_username' => 'u1']);
        $b = $this->insert(['domain_name' => 'dup.com', 'cpanel_username' => 'u2']);
        $c = $this->insert(['domain_name' => 'ok.com', 'cpanel_username' => 'u1']);

        Log::spy();

        try {
            $this->migration()->up();
            $this->fail('Expected DuplicateValuesException');
        } catch (DuplicateValuesException $e) {
            $found = collect($e->duplicates)->keyBy(fn ($d) => $d['column'].':'.$d['value']);
            $this->assertSame([$a, $b], $found['domain_name:dup.com']['ids']);
            $this->assertSame([$a, $c], $found['cpanel_username:u1']['ids']);
        }

        Log::shouldHaveReceived('error')->twice();
        $this->assertFalse(Schema::hasIndex('services', ['cpanel_username'], 'unique'));
        $this->assertFalse(Schema::hasIndex('services', ['domain_name'], 'unique'));
    }

    public function test_many_nulls_pass_and_unique_is_enforced_afterwards(): void
    {
        $this->dropIndexes();
        foreach (range(1, 5) as $_) {
            $this->insert(['domain_name' => null, 'cpanel_username' => null]);
        }

        $this->assertSame([], (new UniqueIndexPreflight)->findDuplicates());
        $this->migration()->up();

        $this->assertTrue(Schema::hasIndex('services', ['domain_name'], 'unique'));
        $this->insert(['domain_name' => null, 'cpanel_username' => null]);

        $this->insert(['domain_name' => 'a.com']);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->insert(['domain_name' => 'a.com']);
    }
}
