<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Exceptions\WhmProvisioningException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\HostingProvisioner;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeWhmService;
use Tests\TestCase;

class HostingProvisionerTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private FakeWhmService $whm;

    private HostingProvisioner $provisioner;

    private NotificationService $notifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->whm = new FakeWhmService();
        $this->notifier = new class extends NotificationService
        {
            /** @var list<array{id: int, level: int, status: ?string}> */
            public array $sent = [];

            public ?\Throwable $throw = null;

            public function notifyCustomerHostingProvisioned(Service $service): void
            {
                $this->sent[] = [
                    'id' => $service->service_id,
                    'level' => DB::transactionLevel(),
                    'status' => DB::table('services')->where('service_id', $service->service_id)->value('provisioning_status'),
                ];
                if ($this->throw) {
                    throw $this->throw;
                }
            }
        };
        $this->provisioner = new HostingProvisioner($this->whm, $this->notifier);
    }

    // Choice (Req 9.8): the email is sent on BOTH create and link success, since
    // in either case the customer's hosting account is now ready. It is never
    // sent when provisioning fails.
    public function test_notification_sent_after_commit_on_create_and_link(): void
    {
        $baseline = DB::transactionLevel(); // RefreshDatabase wraps each test in a transaction

        $created = $this->makeService(['service_package' => 'pkg', 'domain' => 'notify-create.com']);
        $this->assertTrue($this->provisioner->provision($created->service_id));

        $linked = $this->makeService(['service_package' => 'pkg', 'domain' => 'notify-link.com']);
        $this->whm->addAccount('notifylink', 'notify-link.com');
        $this->assertTrue($this->provisioner->provision($linked->service_id));

        $this->assertCount(2, $this->notifier->sent);
        foreach ($this->notifier->sent as $call) {
            $this->assertSame($baseline, $call['level'], 'sent outside the success transaction');
            $this->assertSame('succeeded', $call['status'], 'sent after success was committed');
        }
        $this->assertSame([$created->service_id, $linked->service_id], array_column($this->notifier->sent, 'id'));
    }

    public function test_failing_mailer_leaves_service_succeeded(): void
    {
        $this->notifier->throw = new \RuntimeException('smtp down');
        $service = $this->makeService(['service_package' => 'pkg']);

        $this->assertTrue($this->provisioner->provision($service->service_id));

        $fresh = $service->fresh();
        $this->assertSame('succeeded', $fresh->provisioning_status);
        $this->assertNull($fresh->provisioning_error);
        $this->assertCount(1, $this->notifier->sent);
    }

    public function test_notification_not_sent_on_precondition_failure(): void
    {
        $service = $this->makeService(['product_package' => null]);

        $this->assertFalse($this->provisioner->provision($service->service_id));
        $this->assertSame([], $this->notifier->sent);
    }

    private function makeService(array $opts = []): Service
    {
        $customer = Customer::factory()->create();
        if ($opts['email'] ?? true) {
            $user = User::factory()->create();
            DB::table('users')->where('id', $user->id)->update(['company_id' => $customer->company_id]);
        }

        $attrs = ['company_id' => $customer->company_id, 'domain_name' => $opts['domain'] ?? ('Example'.(++self::$seq).'.COM')];
        $service = Service::factory()->webHosting()->provisioning($opts['status'] ?? 'queued')->create($attrs);

        if (array_key_exists('product_package', $opts)) {
            $product = Product::factory()->hosting()->create();
            DB::table('products')->where('id', $product->id)->update(['whm_package' => $opts['product_package']]);
            $item = OrderItem::factory()->hosting()->create(['product_id' => $product->id]);
            DB::table('services')->where('service_id', $service->service_id)->update(['order_item_id' => $item->id]);
        }
        if (isset($opts['service_package'])) {
            DB::table('services')->where('service_id', $service->service_id)->update(['whm_package' => $opts['service_package']]);
        }

        return $service->fresh();
    }

    public function test_claim_moves_queued_to_running_and_second_claim_fails(): void
    {
        $service = $this->makeService(['service_package' => 'pkg']);

        $this->assertTrue($this->provisioner->claim($service->service_id));
        $this->assertSame('running', $service->fresh()->provisioning_status);
        $this->assertFalse($this->provisioner->claim($service->service_id));
    }

    public function test_succeeded_service_exits_without_whm_calls(): void
    {
        $service = $this->makeService(['status' => 'succeeded', 'service_package' => 'pkg']);

        $this->assertFalse($this->provisioner->provision($service->service_id));
        $this->assertSame('succeeded', $service->fresh()->provisioning_status);
        $this->assertSame(0, $this->whm->totalCalls());
    }

    public function test_resolves_package_from_product_then_service_fallback(): void
    {
        $fromProduct = $this->makeService(['product_package' => 'prod-pkg', 'service_package' => 'svc-pkg']);
        $this->assertSame('prod-pkg', $this->provisioner->resolveInputs($fromProduct)['package']);

        $fallback = $this->makeService(['product_package' => null, 'service_package' => 'svc-pkg']);
        $this->assertSame('svc-pkg', $this->provisioner->resolveInputs($fallback)['package']);
    }

    public function test_resolves_email_and_canonical_domain(): void
    {
        $service = $this->makeService(['service_package' => 'pkg', 'domain' => 'Example.COM']);
        $inputs = $this->provisioner->resolveInputs($service);

        $this->assertSame('example.com', $inputs['domain']);
        $this->assertSame(
            DB::table('users')->where('company_id', $service->company_id)->value('email'),
            $inputs['email']
        );
    }

    public function test_missing_package_marks_failed_without_whm_call(): void
    {
        $service = $this->makeService(['product_package' => null]);

        $this->assertFalse($this->provisioner->provision($service->service_id));

        $fresh = $service->fresh();
        $this->assertSame('failed', $fresh->provisioning_status);
        $this->assertStringContainsString('No WHM package', $fresh->provisioning_error);
        $this->assertSame(0, $this->whm->totalCalls());
    }

    public function test_missing_email_marks_failed_without_whm_call(): void
    {
        $service = $this->makeService(['email' => false, 'service_package' => 'pkg']);

        $this->assertFalse($this->provisioner->provision($service->service_id));

        $fresh = $service->fresh();
        $this->assertSame('failed', $fresh->provisioning_status);
        $this->assertStringContainsString('No customer email', $fresh->provisioning_error);
        $this->assertSame(0, $this->whm->totalCalls());
    }

    public function test_mark_failed_truncates_error(): void
    {
        $service = $this->makeService(['service_package' => 'pkg']);

        $this->provisioner->markFailed($service->service_id, str_repeat('x', 5000));

        $this->assertSame(1000, strlen($service->fresh()->provisioning_error));
    }

    /** Attach the service to a fresh order with one hosting item (plus optional extra items). */
    private function attachOrder(Service $service, array $extraItems = []): Order
    {
        $order = Order::factory()->create(['fulfilment_status' => 'awaiting_fulfilment']);
        $item = OrderItem::factory()->hosting()->create(['order_id' => $order->id]);
        DB::table('services')->where('service_id', $service->service_id)->update(['order_item_id' => $item->id]);
        foreach ($extraItems as $state) {
            OrderItem::factory()->{$state}()->create(['order_id' => $order->id]);
        }

        return $order;
    }

    public function test_creates_account_and_records_success(): void
    {
        $service = $this->makeService(['service_package' => 'pkg', 'domain' => 'Acme-Widgets.com']);
        $order = $this->attachOrder($service);

        $this->assertTrue($this->provisioner->provision($service->service_id));

        $fresh = $service->fresh();
        $this->assertSame('succeeded', $fresh->provisioning_status);
        $this->assertNull($fresh->provisioning_error);
        $this->assertSame('Active', $fresh->status);
        $this->assertSame('acmewidg', $fresh->cpanel_username);
        $this->assertSame(1, $this->whm->callCount('createAccount'));
        $this->assertArrayHasKey('acmewidg', $this->whm->accounts);
        $this->assertSame('completed', $order->fresh()->fulfilment_status);
    }

    public function test_order_not_completed_when_it_has_non_hosting_items(): void
    {
        $service = $this->makeService(['service_package' => 'pkg']);
        $order = $this->attachOrder($service, ['oneOff']);

        $this->provisioner->provision($service->service_id);

        $this->assertSame('succeeded', $service->fresh()->provisioning_status);
        $this->assertSame('awaiting_fulfilment', $order->fresh()->fulfilment_status);
    }

    public function test_order_not_completed_when_another_hosting_item_unprovisioned(): void
    {
        $service = $this->makeService(['service_package' => 'pkg']);
        $order = $this->attachOrder($service, ['hosting']);

        $this->provisioner->provision($service->service_id);

        $this->assertSame('awaiting_fulfilment', $order->fresh()->fulfilment_status);
    }

    public function test_links_existing_account_without_creating(): void
    {
        $service = $this->makeService(['service_package' => 'pkg', 'domain' => 'linked.com']);
        $this->whm->addAccount('linkeduser', 'linked.com');

        $this->assertTrue($this->provisioner->provision($service->service_id));

        $this->assertSame('linkeduser', $service->fresh()->cpanel_username);
        $this->assertSame('succeeded', $service->fresh()->provisioning_status);
        $this->assertSame(0, $this->whm->callCount('createAccount'));
    }

    public function test_refuses_existing_account_owned_by_another_service(): void
    {
        $other = $this->makeService(['service_package' => 'pkg']);
        DB::table('services')->where('service_id', $other->service_id)->update(['cpanel_username' => 'taken1']);
        $service = $this->makeService(['service_package' => 'pkg', 'domain' => 'owned.com']);
        $this->whm->addAccount('taken1', 'owned.com');

        try {
            $this->provisioner->provision($service->service_id);
            $this->fail('Expected WhmProvisioningException');
        } catch (WhmProvisioningException $e) {
            $this->assertStringContainsString((string) $other->service_id, $e->getMessage());
        }

        $fresh = $service->fresh();
        $this->assertSame('failed', $fresh->provisioning_status);
        $this->assertNull($fresh->cpanel_username);
        $this->assertSame(0, $this->whm->callCount('createAccount'));
    }

    public function test_username_collision_picks_next_candidate(): void
    {
        $other = $this->makeService(['service_package' => 'pkg']);
        DB::table('services')->where('service_id', $other->service_id)->update(['cpanel_username' => 'shop']);
        $this->whm->addAccount('shop2', 'unrelated.com');
        $service = $this->makeService(['service_package' => 'pkg', 'domain' => 'shop.com']);

        $this->provisioner->provision($service->service_id);

        $this->assertSame('shop3', $service->fresh()->cpanel_username);
    }

    public function test_valid_free_admin_hint_is_used_and_invalid_hint_ignored(): void
    {
        $a = $this->makeService(['service_package' => 'pkg', 'domain' => 'alpha.com']);
        $this->provisioner->provision($a->service_id, 'custom1');
        $this->assertSame('custom1', $a->fresh()->cpanel_username);

        $b = $this->makeService(['service_package' => 'pkg', 'domain' => 'beta.com']);
        $this->provisioner->provision($b->service_id, 'Bad Name!');
        $this->assertSame('beta', $b->fresh()->cpanel_username);
    }

    public function test_whm_failure_marks_failed_and_touches_no_whm_field(): void
    {
        $service = $this->makeService(['service_package' => 'pkg']);
        $this->whm->failAt('createAccount');

        try {
            $this->provisioner->provision($service->service_id);
            $this->fail('Expected WhmProvisioningException');
        } catch (WhmProvisioningException) {
        }

        $fresh = $service->fresh();
        $this->assertSame('failed', $fresh->provisioning_status);
        $this->assertStringContainsString('Fake WHM failure', $fresh->provisioning_error);
        $this->assertNull($fresh->cpanel_username);
        $this->assertSame($service->status, $fresh->status);
    }

    public function test_failure_after_create_then_retry_links_existing_account(): void
    {
        $service = $this->makeService(['service_package' => 'pkg', 'domain' => 'retry.com']);
        $this->whm->failAfterCreate = true;

        try {
            $this->provisioner->provision($service->service_id);
            $this->fail('Expected WhmProvisioningException');
        } catch (WhmProvisioningException) {
        }
        $this->assertSame('failed', $service->fresh()->provisioning_status);
        $this->assertNull($service->fresh()->cpanel_username);

        $this->whm->clearFailures();
        DB::table('services')->where('service_id', $service->service_id)->update(['provisioning_status' => 'queued']);

        $this->assertTrue($this->provisioner->provision($service->service_id));

        $fresh = $service->fresh();
        $this->assertSame('succeeded', $fresh->provisioning_status);
        $this->assertNull($fresh->provisioning_error);
        $this->assertSame('retry', $fresh->cpanel_username);
        $this->assertSame('Active', $fresh->status);
        $this->assertSame(1, $this->whm->callCount('createAccount'));
    }
}
