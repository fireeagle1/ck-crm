<?php

namespace Tests\Unit\Support;

use App\Exceptions\WhmConnectionException;
use App\Exceptions\WhmProvisioningException;
use App\Services\StripeService;
use App\Services\WhmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\FakeStripeService;
use Tests\Support\FakeWhmService;
use Tests\TestCase;

class FakeServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_fakes_are_substitutable_for_the_real_services(): void
    {
        $this->assertInstanceOf(StripeService::class, new FakeStripeService());
        $this->assertInstanceOf(WhmService::class, new FakeWhmService());
    }

    public function test_stripe_records_calls_and_created_ids(): void
    {
        $stripe = new FakeStripeService();

        $session = $stripe->createCheckoutSessionWithParams(['client_reference_id' => '42', 'metadata' => ['order_id' => '42']]);
        $sub = $stripe->createSubscription('cus_1', 'price_1', ['a' => 'b']);
        $coupon = $stripe->createDiscountCoupon(['percent_off' => 10]);

        $this->assertSame([$session->id], $stripe->createdIds('session'));
        $this->assertSame([$sub->id], $stripe->createdIds('subscription'));
        $this->assertSame([$coupon->id], $stripe->createdIds('coupon'));
        $this->assertSame('42', $session->client_reference_id);
        $this->assertSame(1, $stripe->callCount('createSubscription'));
        $this->assertSame('price_1', $stripe->callsTo('createSubscription')[0]['args']['priceId']);
        $this->assertSame(3, count($stripe->calls));
    }

    public function test_stripe_records_transaction_level_at_each_call(): void
    {
        $stripe = new FakeStripeService();

        $stripe->createSubscription('cus_1', 'price_1');
        DB::transaction(fn () => $stripe->createSubscription('cus_1', 'price_2'));

        [$outside, $inside] = $stripe->transactionLevels();
        $this->assertSame(DB::transactionLevel(), $outside);
        $this->assertSame($outside + 1, $inside);
    }

    public function test_stripe_fails_at_chosen_step_only_and_creates_nothing(): void
    {
        $stripe = (new FakeStripeService())->failAt('createSubscription', null, 1);

        $stripe->createSubscription('cus_1', 'price_1'); // allowed through
        try {
            $stripe->createSubscription('cus_1', 'price_2');
            $this->fail('Expected failure');
        } catch (HttpException $e) {
            $this->assertSame(502, $e->getStatusCode());
        }

        $this->assertCount(1, $stripe->createdIds('subscription'));
        $this->assertSame(2, $stripe->callCount('createSubscription'), 'failed call is still recorded');
        // Other steps unaffected
        $this->assertNotEmpty($stripe->createCheckoutSessionWithParams([])->id);

        $stripe->clearFailures();
        $stripe->createSubscription('cus_1', 'price_3');
        $this->assertCount(2, $stripe->createdIds('subscription'));
    }

    public function test_stripe_records_cancelled_ids(): void
    {
        $stripe = new FakeStripeService();

        $stripe->expireCheckoutSession('cs_1');
        $stripe->cancelSubscription('sub_1');

        $this->assertSame(['cs_1'], $stripe->cancelledIds('session'));
        $this->assertSame(['sub_1'], $stripe->cancelledIds('subscription'));
        $this->assertSame([], $stripe->cancelledIds('coupon'));
    }

    public function test_whm_creates_finds_and_lists_accounts(): void
    {
        $whm = (new FakeWhmService())->withAccounts([['user' => 'old', 'domain' => 'old.com']]);

        $result = $whm->createAccount('example.com', 'pkg', 'a@b.com');

        $this->assertSame('examplec', $result['username']); // first 8 chars of "examplecom"
        $this->assertSame('example.com', $whm->findAccountByDomain('example.com')['domain']);
        $this->assertNull($whm->findAccountByDomain('nope.com'));
        $this->assertTrue($whm->accountExists('old'));
        $this->assertFalse($whm->accountExists('zzz'));
        $this->assertCount(2, $whm->listAccounts());
        $this->assertSame('pkg', $whm->getAccountInfo('examplec')['plan']);
        $this->assertStringContainsString('user=old', $whm->createUserSession('old', 'cpaneld'));
    }

    public function test_whm_honours_requested_username_and_rejects_collisions(): void
    {
        $whm = (new FakeWhmService())->addAccount('taken', 'taken.com');

        $this->assertSame('mine', $whm->createAccount('x.com', 'pkg', 'a@b.com', 'mine')['username']);

        $this->expectException(WhmProvisioningException::class);
        $whm->createAccount('y.com', 'pkg', 'a@b.com', 'taken');
    }

    public function test_whm_counts_calls_and_fails_on_demand(): void
    {
        $whm = (new FakeWhmService())->failAt('createAccount');

        try {
            $whm->createAccount('x.com', 'pkg', 'a@b.com');
            $this->fail('Expected failure');
        } catch (WhmProvisioningException) {
        }

        $this->assertSame(1, $whm->callCount('createAccount'));
        $this->assertSame([], $whm->accounts, 'failure leaves no account');
        $this->assertSame(0, $whm->callCount('listAccounts'));

        $whm->failAt('listAccounts');
        $this->expectException(WhmConnectionException::class);
        $whm->listAccounts();
    }

    public function test_whm_can_fail_after_account_creation(): void
    {
        $whm = new FakeWhmService();
        $whm->failAfterCreate = true;

        try {
            $whm->createAccount('x.com', 'pkg', 'a@b.com');
            $this->fail('Expected failure');
        } catch (WhmProvisioningException) {
        }

        $this->assertNotNull($whm->findAccountByDomain('x.com'), 'account exists despite the exception');
    }
}
