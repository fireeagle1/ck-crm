<?php

namespace App\ValueObjects;

use App\Models\Order;

class CheckoutResult
{
    public function __construct(
        public readonly ?string $checkoutSessionUrl,
        public readonly ?Order $order,
        public readonly array $subscriptionIds,
        public readonly bool $success,
        public readonly ?string $errorMessage,
    ) {}
}
