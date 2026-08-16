<?php

namespace App\Services;

use App\Models\DiscountCode;

class DiscountCodeService
{
    /**
     * Validate a discount code against the given order total.
     *
     * @param string $code The discount code entered by the customer.
     * @param float $orderTotal The subtotal of items (before delivery/discount).
     * @return array{valid: bool, message: ?string, discount_amount: float}
     */
    public function validate(string $code, float $orderTotal): array
    {
        $discountCode = DiscountCode::where('code', strtoupper(trim($code)))->first();

        if (!$discountCode) {
            return [
                'valid' => false,
                'message' => 'Invalid discount code.',
                'discount_amount' => 0,
            ];
        }

        if (!$discountCode->is_active) {
            return [
                'valid' => false,
                'message' => 'This discount code is no longer active.',
                'discount_amount' => 0,
            ];
        }

        if ($discountCode->valid_from && now()->lt($discountCode->valid_from)) {
            return [
                'valid' => false,
                'message' => 'This discount code is not yet valid.',
                'discount_amount' => 0,
            ];
        }

        if ($discountCode->valid_until && now()->gt($discountCode->valid_until)) {
            return [
                'valid' => false,
                'message' => 'This discount code has expired.',
                'discount_amount' => 0,
            ];
        }

        if ($discountCode->usage_limit !== null && $discountCode->times_used >= $discountCode->usage_limit) {
            return [
                'valid' => false,
                'message' => 'This discount code has reached its usage limit.',
                'discount_amount' => 0,
            ];
        }

        if ($discountCode->min_order_amount !== null && $orderTotal < (float) $discountCode->min_order_amount) {
            return [
                'valid' => false,
                'message' => "Minimum order of £" . number_format($discountCode->min_order_amount, 2) . " required for this code.",
                'discount_amount' => 0,
            ];
        }

        $discountAmount = $discountCode->calculateDiscount($orderTotal);

        return [
            'valid' => true,
            'message' => null,
            'discount_amount' => $discountAmount,
        ];
    }

    /**
     * Increment the usage count for a discount code.
     */
    public function incrementUsage(string $code): void
    {
        DiscountCode::where('code', strtoupper(trim($code)))
            ->increment('times_used');
    }
}
