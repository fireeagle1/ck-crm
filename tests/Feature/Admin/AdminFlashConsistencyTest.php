<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerApp;
use App\Models\Domain;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The admin layout is the single place flash success/error and validation
 * messages are rendered, so each appears exactly once on every screen.
 *
 * Validates: Requirements 22.1
 */
class AdminFlashConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: callable}> */
    public static function screens(): array
    {
        return [
            'services index' => [fn () => route('admin.services.index')],
            'services create' => [fn () => route('admin.services.create')],
            'services show' => [fn () => route('admin.services.show', Service::factory()->webHosting()->create())],
            'services edit' => [fn () => route('admin.services.edit', Service::factory()->webHosting()->create())],
            'domains index' => [fn () => route('admin.domains.index')],
            'domains create' => [fn () => route('admin.domains.create')],
            'domains edit' => [fn () => route('admin.domains.edit', Domain::factory()->create())],
            'customer-apps index' => [fn () => route('admin.customer-apps.index')],
            'customer-apps create' => [fn () => route('admin.customer-apps.create', Service::factory()->webHosting()->create())],
            'customer-apps show' => [fn () => route('admin.customer-apps.show', CustomerApp::factory()->create())],
            'customer-apps edit' => [fn () => route('admin.customer-apps.edit', CustomerApp::factory()->create())],
            'hosting provisioning' => [fn () => route('admin.hosting.provision.index')],
        ];
    }

    #[DataProvider('screens')]
    public function test_flash_and_validation_messages_render_exactly_once(callable $url): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->is_admin = true;
        $admin->save();

        // Sessions use JSON serialization, so the error bag is seeded in its
        // serialized shape; the session store marshals it back into a
        // ViewErrorBag on start, exactly as after a real validation redirect.
        $errors = ['default' => ['format' => ':message', 'messages' => ['general' => ['Validation-marker-XYZ']]]];

        $response = $this->actingAs($admin)
            ->withSession([
                'success' => 'Success-marker-XYZ',
                'error' => 'Error-marker-XYZ',
                'errors' => $errors,
            ])
            ->get($url());

        $response->assertOk();
        $html = $response->getContent();
        foreach (['Success-marker-XYZ', 'Error-marker-XYZ', 'Validation-marker-XYZ'] as $marker) {
            $this->assertSame(1, substr_count($html, $marker), "{$marker} should render exactly once");
        }
    }
}
