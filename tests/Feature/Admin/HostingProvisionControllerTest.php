<?php

namespace Tests\Feature\Admin;

use App\Jobs\ProvisionHostingJob;
use App\Models\Service;
use App\Models\User;
use App\Services\WhmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeWhmService;
use Tests\TestCase;

/**
 * Validates: Requirements 9.11, 9.12, 9.13, 22.2
 */
class HostingProvisionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private FakeWhmService $whm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->is_admin = true;
        $this->admin->save();

        config(['queue.default' => 'database']);
        Queue::fake();

        $this->whm = new FakeWhmService();
        $this->app->instance(WhmService::class, $this->whm);
    }

    private function hosting(?string $status): Service
    {
        $factory = Service::factory()->webHosting();
        if ($status !== null) {
            $factory = $factory->provisioning($status);
        }

        return $factory->create(['domain_name' => uniqid('ctrl-').'.com']);
    }

    public function test_index_lists_all_hosting_services_with_required_fields(): void
    {
        $product = \App\Models\Product::factory()->create(['name' => 'Gold Shop Hosting', 'product_type' => 'hosting']);
        $item = \App\Models\OrderItem::factory()->hosting()->create(['product_id' => $product->id]);
        $shop = Service::factory()->webHosting()->provisioning('succeeded')
            ->create(['domain_name' => 'shop-bought.example.com', 'order_item_id' => $item->id]);
        $manual = Service::factory()->webHosting()->create(['domain_name' => 'hand-made.example.com', 'service_short' => 'Manual Plan']);
        $failed = Service::factory()->webHosting()->provisioning('failed')->create(['domain_name' => 'broken.example.com']);
        Service::factory()->create(['service_type' => 'Technical Support', 'domain_name' => 'support.example.com']);

        $this->actingAs($this->admin)
            ->get(route('admin.hosting.provision.index'))
            ->assertOk()
            ->assertSeeText('shop-bought.example.com')
            ->assertSeeText('hand-made.example.com')
            ->assertSeeText('broken.example.com')
            ->assertDontSeeText('support.example.com')
            ->assertSeeText('Gold Shop Hosting')
            ->assertSeeText('Manual Plan')
            ->assertSeeText('Factory failure')
            ->assertSeeText($failed->customer->company_name)
            ->assertSeeText($shop->created_at->format('d M Y'))
            ->assertSeeText('Succeeded')
            ->assertSeeText('Not tracked');
    }

    public function test_index_status_filter_returns_only_matching_rows(): void
    {
        $this->hosting('queued')->update(['domain_name' => 'f-queued.example.com']);
        $this->hosting('failed')->update(['domain_name' => 'f-failed.example.com']);
        $this->hosting('succeeded')->update(['domain_name' => 'f-ok.example.com']);
        $this->hosting(null)->update(['domain_name' => 'f-null.example.com']);

        $all = ['f-queued', 'f-failed', 'f-ok', 'f-null'];
        $cases = ['queued' => 'f-queued', 'failed' => 'f-failed', 'succeeded' => 'f-ok', 'none' => 'f-null'];

        foreach ($cases as $filter => $expected) {
            $response = $this->actingAs($this->admin)
                ->get(route('admin.hosting.provision.index', ['status' => $filter]))
                ->assertOk()
                ->assertSeeText($expected.'.example.com');
            foreach (array_diff($all, [$expected]) as $other) {
                $response->assertDontSeeText($other.'.example.com');
            }
        }
    }

    public function test_index_paginates_25_and_preserves_query_string(): void
    {
        Service::factory()->webHosting()->provisioning('failed')->count(27)->create();
        Service::factory()->webHosting()->provisioning('succeeded')->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.hosting.provision.index', ['status' => 'failed']))
            ->assertOk();

        $this->assertCount(25, $response->viewData('services')->items());
        $this->assertSame(27, $response->viewData('services')->total());
        $response->assertSee('status=failed', false);
        $response->assertSee('page=2', false);

        $page2 = $this->actingAs($this->admin)
            ->get(route('admin.hosting.provision.index', ['status' => 'failed', 'page' => 2]));
        $this->assertCount(2, $page2->viewData('services')->items());
    }

    public function test_provision_queues_job_without_calling_whm(): void
    {
        $service = $this->hosting(null);

        $this->actingAs($this->admin)
            ->post(route('admin.hosting.provision.provision', $service))
            ->assertRedirect(route('admin.hosting.provision.index'))
            ->assertSessionHas('success');

        Queue::assertPushed(ProvisionHostingJob::class, 1);
        $this->assertSame('queued', $service->fresh()->provisioning_status);
        $this->assertSame(0, $this->whm->totalCalls());
    }

    public function test_retry_only_accepted_from_failed(): void
    {
        foreach ([null, 'queued', 'running', 'succeeded'] as $status) {
            $service = $this->hosting($status);

            $this->actingAs($this->admin)
                ->post(route('admin.hosting.provision.retry', $service))
                ->assertSessionHas('error');

            $this->assertSame($status, $service->fresh()->provisioning_status);
        }
        Queue::assertNothingPushed();

        $failed = $this->hosting('failed');
        $this->actingAs($this->admin)
            ->post(route('admin.hosting.provision.retry', $failed))
            ->assertSessionHas('success');

        Queue::assertPushed(ProvisionHostingJob::class, 1);
        $this->assertSame('queued', $failed->fresh()->provisioning_status);
        $this->assertSame(0, $this->whm->totalCalls());
    }

    public function test_action_buttons_render_for_the_right_rows_only(): void
    {
        $failed = Service::factory()->webHosting()->provisioning('failed')->create(['domain_name' => 'btn-failed.example.com']);
        $pending = Service::factory()->webHosting()->pending()->create(['domain_name' => 'btn-pending.example.com']);
        $active = Service::factory()->webHosting()->create(['domain_name' => 'btn-active.example.com']);
        $queued = Service::factory()->webHosting()->provisioning('queued')->create(['domain_name' => 'btn-queued.example.com']);
        $ok = Service::factory()->webHosting()->provisioning('succeeded')->create(['domain_name' => 'btn-ok.example.com']);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.hosting.provision.index'))
            ->assertOk()
            ->getContent();

        $retry = fn (Service $s) => route('admin.hosting.provision.retry', $s);
        $provision = fn (Service $s) => route('admin.hosting.provision.provision', $s);

        $this->assertStringContainsString($retry($failed), $html);
        $this->assertStringContainsString($provision($pending), $html);

        $this->assertStringNotContainsString($provision($failed), $html);
        $this->assertStringNotContainsString($retry($pending), $html);
        foreach ([$active, $queued, $ok] as $service) {
            $this->assertStringNotContainsString($retry($service), $html);
            $this->assertStringNotContainsString($provision($service), $html);
        }
    }

    public function test_retry_shows_updated_status_on_queue_page(): void
    {
        $failed = $this->hosting('failed');

        $this->actingAs($this->admin)
            ->followingRedirects()
            ->post(route('admin.hosting.provision.retry', $failed))
            ->assertOk()
            ->assertSeeText('Queued')
            ->assertDontSee(route('admin.hosting.provision.retry', $failed), false);

        $this->assertSame('queued', $failed->fresh()->provisioning_status);
    }

    public function test_routes_are_admin_only(): void
    {
        $service = $this->hosting('failed');
        $user = User::factory()->create(['email_verified_at' => now()]);

        foreach (['provision', 'retry'] as $name) {
            $this->actingAs($user)
                ->post(route("admin.hosting.provision.$name", $service))
                ->assertForbidden();
        }

        $this->assertSame('failed', $service->fresh()->provisioning_status);
        Queue::assertNothingPushed();
    }
}
