<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->is_admin = true;
        $this->admin->save();
    }

    // ──────────────────────────────────────────────────────────────────
    // Control test: Valid product creation succeeds
    // ──────────────────────────────────────────────────────────────────

    public function test_valid_one_off_product_can_be_created(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.shop.products.store'), [
            'name' => 'Test Product',
            'description' => 'A valid one-off product',
            'product_type' => 'one_off',
            'price' => 29.99,
            'billing_frequency' => null,
            'stock_quantity' => 10,
            'visibility_type' => 'all',
        ]);

        $response->assertRedirect(route('admin.shop.products.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
            'product_type' => 'one_off',
            'price' => 29.99,
        ]);
    }

    public function test_valid_hosting_product_can_be_created(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.shop.products.store'), [
            'name' => 'Hosting Plan',
            'description' => 'A valid hosting product',
            'product_type' => 'hosting',
            'price' => 9.99,
            'billing_frequency' => 'monthly',
            'visibility_type' => 'all',
        ]);

        $response->assertRedirect(route('admin.shop.products.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'name' => 'Hosting Plan',
            'product_type' => 'hosting',
            'billing_frequency' => 'monthly',
        ]);
    }

    public function test_valid_equipment_rental_product_can_be_created(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.shop.products.store'), [
            'name' => 'Equipment Rental',
            'description' => 'A valid equipment rental product',
            'product_type' => 'equipment_rental',
            'price' => 49.99,
            'billing_frequency' => 'quarterly',
            'stock_quantity' => 5,
            'visibility_type' => 'all',
        ]);

        $response->assertRedirect(route('admin.shop.products.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'name' => 'Equipment Rental',
            'product_type' => 'equipment_rental',
            'billing_frequency' => 'quarterly',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Property 1: Product Validation Rejects Incomplete Data
    // **Validates: Requirements 1.2**
    // ──────────────────────────────────────────────────────────────────

    /**
     * **Validates: Requirements 1.2**
     *
     * Property 1: For ALL product submissions missing one or more required fields
     * (name, description, product_type, price), the admin panel SHALL reject
     * the submission with validation errors.
     */
    public function test_property_product_validation_rejects_missing_name(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.shop.products.store'), [
            // name is missing
            'description' => 'Some description',
            'product_type' => 'one_off',
            'price' => 10.00,
            'visibility_type' => 'all',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_property_product_validation_rejects_missing_description(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.shop.products.store'), [
            'name' => 'Test Product',
            // description is missing
            'product_type' => 'one_off',
            'price' => 10.00,
            'visibility_type' => 'all',
        ]);

        $response->assertSessionHasErrors('description');
    }

    public function test_property_product_validation_rejects_missing_product_type(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.shop.products.store'), [
            'name' => 'Test Product',
            'description' => 'Some description',
            // product_type is missing
            'price' => 10.00,
            'visibility_type' => 'all',
        ]);

        $response->assertSessionHasErrors('product_type');
    }

    public function test_property_product_validation_rejects_missing_price(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.shop.products.store'), [
            'name' => 'Test Product',
            'description' => 'Some description',
            'product_type' => 'one_off',
            // price is missing
            'visibility_type' => 'all',
        ]);

        $response->assertSessionHasErrors('price');
    }

    public function test_property_product_validation_rejects_invalid_product_type(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.shop.products.store'), [
            'name' => 'Test Product',
            'description' => 'Some description',
            'product_type' => 'invalid_type',
            'price' => 10.00,
            'visibility_type' => 'all',
        ]);

        $response->assertSessionHasErrors('product_type');
    }

    public function test_property_product_validation_rejects_zero_price(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.shop.products.store'), [
            'name' => 'Test Product',
            'description' => 'Some description',
            'product_type' => 'one_off',
            'price' => 0,
            'visibility_type' => 'all',
        ]);

        $response->assertSessionHasErrors('price');
    }

    /**
     * **Validates: Requirements 1.2**
     *
     * Property test variant: generate many random product payloads missing
     * different required fields, all should fail validation.
     */
    public function test_property_random_incomplete_payloads_always_rejected(): void
    {
        $requiredFields = ['name', 'description', 'product_type', 'price'];
        $iterations = 30;

        for ($i = 0; $i < $iterations; $i++) {
            // Randomly select 1-3 fields to omit
            $numFieldsToOmit = random_int(1, 3);
            shuffle($requiredFields);
            $fieldsToOmit = array_slice($requiredFields, 0, $numFieldsToOmit);

            $payload = [
                'name' => 'Product ' . $i,
                'description' => 'Description for product ' . $i,
                'product_type' => 'one_off',
                'price' => round(random_int(100, 99999) / 100, 2),
                'visibility_type' => 'all',
            ];

            // Remove the randomly selected fields
            foreach ($fieldsToOmit as $field) {
                unset($payload[$field]);
            }

            $response = $this->actingAs($this->admin)
                ->from('/admin/shop/products/create')
                ->post(route('admin.shop.products.store'), $payload);

            // Should redirect back (validation failure) rather than to index (success)
            $response->assertRedirect('/admin/shop/products/create');

            // Each omitted field should produce a validation error
            foreach ($fieldsToOmit as $field) {
                $response->assertSessionHasErrors($field);
            }

            // Flush session errors between iterations
            session()->forget('errors');
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Property 2: Recurring Product Requires Billing Frequency
    // **Validates: Requirements 1.3**
    // ──────────────────────────────────────────────────────────────────

    /**
     * **Validates: Requirements 1.3**
     *
     * Property 2: For ALL products of type 'equipment_rental' or 'hosting',
     * the admin panel SHALL require a billing_frequency field. Submissions
     * without billing_frequency for these types SHALL be rejected.
     */
    public function test_property_hosting_product_requires_billing_frequency(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.shop.products.store'), [
            'name' => 'Hosting Product',
            'description' => 'A hosting product without billing frequency',
            'product_type' => 'hosting',
            'price' => 15.99,
            // billing_frequency is missing
            'visibility_type' => 'all',
        ]);

        $response->assertSessionHasErrors('billing_frequency');
    }

    public function test_property_equipment_rental_does_not_require_billing_frequency(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.shop.products.store'), [
            'name' => 'Equipment Rental',
            'description' => 'An equipment rental priced per day',
            'product_type' => 'equipment_rental',
            'price' => 25.00,
            // billing_frequency is NOT required for equipment_rental (per-day pricing)
            'visibility_type' => 'all',
        ]);

        $response->assertSessionDoesntHaveErrors('billing_frequency');
    }

    public function test_property_one_off_product_does_not_require_billing_frequency(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.shop.products.store'), [
            'name' => 'One-Off Product',
            'description' => 'A one-off product without billing frequency',
            'product_type' => 'one_off',
            'price' => 19.99,
            // billing_frequency is intentionally not provided
            'visibility_type' => 'all',
        ]);

        $response->assertSessionDoesntHaveErrors('billing_frequency');
        $response->assertRedirect(route('admin.shop.products.index'));
    }

    /**
     * **Validates: Requirements 1.3**
     *
     * Property test variant: generate many random hosting product payloads
     * (hosting) without billing_frequency, all should fail.
     */
    public function test_property_random_recurring_products_without_billing_frequency_rejected(): void
    {
        $recurringTypes = ['hosting'];
        $iterations = 20;

        for ($i = 0; $i < $iterations; $i++) {
            $type = $recurringTypes[array_rand($recurringTypes)];

            $response = $this->actingAs($this->admin)
                ->from('/admin/shop/products/create')
                ->post(route('admin.shop.products.store'), [
                    'name' => 'Recurring Product ' . $i,
                    'description' => 'A recurring product without billing frequency',
                    'product_type' => $type,
                    'price' => round(random_int(100, 99999) / 100, 2),
                    // billing_frequency deliberately omitted
                    'visibility_type' => 'all',
                ]);

            $response->assertSessionHasErrors(
                'billing_frequency',
                "Property 2 failed: product_type '{$type}' without billing_frequency should be rejected (iteration {$i})."
            );

            // Flush session errors between iterations
            session()->forget('errors');
        }
    }

    /**
     * **Validates: Requirements 1.3**
     *
     * Verify that valid billing frequencies are accepted for recurring types.
     */
    public function test_property_recurring_products_with_valid_billing_frequency_accepted(): void
    {
        $testCases = [
            ['product_type' => 'hosting', 'billing_frequency' => 'monthly'],
            ['product_type' => 'hosting', 'billing_frequency' => 'quarterly'],
            ['product_type' => 'hosting', 'billing_frequency' => 'annually'],
            ['product_type' => 'equipment_rental', 'billing_frequency' => 'monthly'],
            ['product_type' => 'equipment_rental', 'billing_frequency' => 'quarterly'],
            ['product_type' => 'equipment_rental', 'billing_frequency' => 'annually'],
        ];

        foreach ($testCases as $index => $case) {
            $response = $this->actingAs($this->admin)->post(route('admin.shop.products.store'), [
                'name' => "Recurring Product {$index}",
                'description' => 'Valid recurring product',
                'product_type' => $case['product_type'],
                'price' => 19.99,
                'billing_frequency' => $case['billing_frequency'],
                'visibility_type' => 'all',
            ]);

            $response->assertSessionDoesntHaveErrors('billing_frequency');
            $response->assertRedirect(route('admin.shop.products.index'));
        }
    }
}
