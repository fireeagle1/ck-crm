<?php

namespace Tests\Unit\Support;

use Tests\TestCase;

class ShopConfigDefaultsTest extends TestCase
{
    public function test_stripe_currency_defaults_to_gbp(): void
    {
        $this->assertSame('gbp', config('services.stripe.currency'));
    }

    public function test_abandoned_hosting_hours_defaults_to_48(): void
    {
        $this->assertSame(48, config('shop.abandoned_hosting_hours'));
    }
}
