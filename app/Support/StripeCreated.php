<?php

namespace App\Support;

/**
 * Immutable record of the Stripe objects created while processing a checkout.
 *
 * Used for best-effort compensation when the checkout fails or the order is
 * abandoned (Req 5.3, 5.4). Each `with*` method returns a new instance.
 */
final class StripeCreated
{
    /**
     * @param list<string> $subscriptionIds
     */
    public function __construct(
        public readonly ?string $sessionId = null,
        public readonly array $subscriptionIds = [],
        public readonly ?string $couponId = null,
    ) {
    }

    public function withSession(string $sessionId): self
    {
        return new self($sessionId, $this->subscriptionIds, $this->couponId);
    }

    public function withSubscription(string $subscriptionId): self
    {
        return new self(
            $this->sessionId,
            array_values(array_unique([...$this->subscriptionIds, $subscriptionId])),
            $this->couponId
        );
    }

    public function withCoupon(string $couponId): self
    {
        return new self($this->sessionId, $this->subscriptionIds, $couponId);
    }

    /** Merge another record into this one (the other wins for session/coupon when set). */
    public function merge(self $other): self
    {
        return new self(
            $other->sessionId ?? $this->sessionId,
            array_values(array_unique([...$this->subscriptionIds, ...$other->subscriptionIds])),
            $other->couponId ?? $this->couponId,
        );
    }

    public function isEmpty(): bool
    {
        return $this->sessionId === null && $this->subscriptionIds === [] && $this->couponId === null;
    }
}
