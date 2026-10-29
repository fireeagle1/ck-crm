<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schema example tests for the Customer App Services feature.
 *
 * Asserts the three cascading tables (customer_apps, app_packages,
 * security_findings) exist with their expected columns, that
 * customer_apps.service_id is a unique index, that the foreign keys
 * cascade on delete, and that customer_apps carries no billing column.
 *
 * _Requirements: 1.1, 1.2, 1.5, 2.1_
 */
class CustomerAppSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('customer_apps'), 'customer_apps table is missing');
        $this->assertTrue(Schema::hasTable('app_packages'), 'app_packages table is missing');
        $this->assertTrue(Schema::hasTable('security_findings'), 'security_findings table is missing');
    }

    public function test_customer_apps_has_expected_columns(): void
    {
        // Req 1.1, 1.2: repo URL, hosting location, codebase info, and the service_id FK.
        $this->assertTrue(Schema::hasColumns('customer_apps', [
            'id',
            'service_id',
            'display_name',
            'install_url',
            'repo_url',
            'hosting_location',
            'codebase_info',
            'created_at',
            'updated_at',
        ]), 'customer_apps is missing one or more expected columns');
    }

    public function test_customer_apps_has_no_billing_columns(): void
    {
        // Req 1.5: billing is single-sourced on the Service record and must not be
        // duplicated onto the technical CustomerApp table.
        $billingColumns = [
            'service_monthly_charge',
            'service_payment_frequency',
            'stripe_subscription_id',
            'stripe_price_id',
            'billing',
            'price',
            'amount',
            'next_payment_date',
        ];

        foreach ($billingColumns as $column) {
            $this->assertFalse(
                Schema::hasColumn('customer_apps', $column),
                "customer_apps must not contain billing column [{$column}]"
            );
        }
    }

    public function test_app_packages_has_expected_columns(): void
    {
        // Req 2.1: each package records a name and a version, linked to a CustomerApp.
        $this->assertTrue(Schema::hasColumns('app_packages', [
            'id',
            'customer_app_id',
            'name',
            'version',
            'synced_at',
            'created_at',
            'updated_at',
        ]), 'app_packages is missing one or more expected columns');
    }

    public function test_security_findings_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('security_findings', [
            'id',
            'customer_app_id',
            'severity',
            'description',
            'status',
            'synced_at',
            'created_at',
            'updated_at',
        ]), 'security_findings is missing one or more expected columns');
    }

    public function test_service_id_is_a_unique_index(): void
    {
        // Req 1.2 / one-to-one invariant: service_id must carry a unique index so a
        // Service can have at most one CustomerApp.
        $hasUniqueOnServiceId = collect(Schema::getIndexes('customer_apps'))
            ->contains(fn (array $index): bool => $index['unique']
                && $index['columns'] === ['service_id']);

        $this->assertTrue(
            $hasUniqueOnServiceId,
            'customer_apps.service_id must have a unique index'
        );
    }

    public function test_service_deletion_cascades_to_customer_app_and_children(): void
    {
        // Req 1.2, 2.1, and the cascade chain: deleting a Service removes its
        // CustomerApp and every child package and finding, leaving no orphans.
        $companyId = DB::table('customers')->insertGetId([
            'company_name' => 'Acme Ltd',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'company_id');

        $serviceId = DB::table('services')->insertGetId([
            'company_id' => $companyId,
            'service_short' => 'Managed App',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'service_id');

        $customerAppId = DB::table('customer_apps')->insertGetId([
            'service_id' => $serviceId,
            'repo_url' => 'https://example.com/repo.git',
            'hosting_location' => 'eu-west-1',
            'codebase_info' => 'Laravel 13 / PHP 8.3',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('app_packages')->insert([
            'customer_app_id' => $customerAppId,
            'name' => 'laravel/framework',
            'version' => '13.0.0',
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('security_findings')->insert([
            'customer_app_id' => $customerAppId,
            'severity' => 'high',
            'description' => 'Example finding',
            'status' => 'open',
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Sanity: the rows exist before deletion.
        $this->assertSame(1, DB::table('customer_apps')->where('service_id', $serviceId)->count());
        $this->assertSame(1, DB::table('app_packages')->where('customer_app_id', $customerAppId)->count());
        $this->assertSame(1, DB::table('security_findings')->where('customer_app_id', $customerAppId)->count());

        // Delete the Service and assert the whole technical subtree is gone.
        DB::table('services')->where('service_id', $serviceId)->delete();

        $this->assertSame(0, DB::table('customer_apps')->where('service_id', $serviceId)->count(), 'customer_apps row was not cascade-deleted');
        $this->assertSame(0, DB::table('app_packages')->where('customer_app_id', $customerAppId)->count(), 'app_packages row was not cascade-deleted');
        $this->assertSame(0, DB::table('security_findings')->where('customer_app_id', $customerAppId)->count(), 'security_findings row was not cascade-deleted');
    }
}
