<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\ValueObjects\CheckoutResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutService
{
    public function __construct(private StripeService $stripe)
    {
    }

    /**
     * Process checkout for the given customer and cart items.
     *
     * Partitions items into one-off and recurring groups, processes each via
     * the appropriate Stripe mechanism, creates Order/OrderItem records, and
     * returns a CheckoutResult value object.
     *
     * @param Customer $customer
     * @param array<int, array{product_id: int, name: string, price: float, product_type: string, billing_frequency: ?string}> $cartItems
     * @return CheckoutResult
     */
    public function processCheckout(Customer $customer, array $cartItems): CheckoutResult
    {
        try {
            // Ensure the customer has a Stripe customer record
            $this->stripe->ensureCustomer($customer);

            // Partition items into one-off and recurring groups
            $oneOffItems = array_values(
                array_filter($cartItems, fn (array $item) => $item['product_type'] === 'one_off')
            );
            $recurringItems = array_values(
                array_filter(
                    $cartItems,
                    fn (array $item) => in_array($item['product_type'], ['equipment_rental', 'hosting'], true)
                )
            );

            // Process each group
            $checkoutSessionUrl = $this->processOneOffItems($customer, $oneOffItems);
            $subscriptionIds = $this->processRecurringItems($customer, $recurringItems);

            // Determine the stripe_checkout_session_id for the order (extract from URL if present)
            $sessionId = null;
            if ($checkoutSessionUrl) {
                // The session ID is stored on the customer object via the Stripe session creation
                // We need to extract it — use a separate approach: create the session and capture ID
                $sessionId = $this->lastCheckoutSessionId;
            }

            // Create Order and OrderItems within a transaction
            $order = $this->createOrderFromCheckout($customer, $cartItems, $sessionId, $subscriptionIds);

            return new CheckoutResult(
                checkoutSessionUrl: $checkoutSessionUrl,
                order: $order,
                subscriptionIds: $subscriptionIds,
                success: true,
                errorMessage: null,
            );
        } catch (\Throwable $e) {
            Log::error('Checkout failed', [
                'company_id' => $customer->company_id,
                'error' => $e->getMessage(),
            ]);

            return new CheckoutResult(
                checkoutSessionUrl: null,
                order: null,
                subscriptionIds: [],
                success: false,
                errorMessage: $e->getMessage(),
            );
        }
    }

    /**
     * Track the last checkout session ID for order creation.
     */
    private ?string $lastCheckoutSessionId = null;

    /**
     * Process one-off items by creating a Stripe Checkout Session.
     *
     * @param Customer $customer
     * @param array $items One-off cart items
     * @return string|null The checkout session URL, or null if no one-off items
     */
    private function processOneOffItems(Customer $customer, array $items): ?string
    {
        if (empty($items)) {
            $this->lastCheckoutSessionId = null;
            return null;
        }

        $lineItems = [];
        foreach ($items as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'gbp',
                    'product_data' => [
                        'name' => $item['name'],
                    ],
                    'unit_amount' => (int) round($item['price'] * 100), // Convert to pence
                ],
                'quantity' => 1,
            ];
        }

        $successUrl = route('portal.orders.index') . '?checkout=success';
        $cancelUrl = route('portal.cart.index') . '?checkout=cancelled';

        $session = $this->stripe->createCheckoutSession(
            $customer->stripe_customer_id,
            $lineItems,
            $successUrl,
            $cancelUrl,
        );

        $this->lastCheckoutSessionId = $session->id;

        return $session->url;
    }

    /**
     * Process recurring items by creating a Stripe Subscription for each.
     *
     * @param Customer $customer
     * @param array $items Recurring cart items (equipment_rental or hosting)
     * @return array List of Stripe subscription IDs
     */
    private function processRecurringItems(Customer $customer, array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $subscriptionIds = [];

        foreach ($items as $item) {
            // Build a price ID from product metadata
            // In production this would come from a stripe_price_id on the product;
            // for now we use a placeholder convention based on product_id
            $priceId = $item['stripe_price_id'] ?? 'price_placeholder_' . $item['product_id'];

            $metadata = [
                'product_id' => $item['product_id'],
                'product_name' => $item['name'],
                'product_type' => $item['product_type'],
                'company_id' => $customer->company_id,
            ];

            $subscription = $this->stripe->createSubscription(
                $customer->stripe_customer_id,
                $priceId,
                $metadata,
            );

            $subscriptionIds[] = $subscription->id;
        }

        return $subscriptionIds;
    }

    /**
     * Create Order and OrderItem records from the checkout.
     *
     * @param Customer $customer
     * @param array $items All cart items
     * @param string|null $sessionId Stripe checkout session ID (for one-off items)
     * @param array $subscriptionIds Stripe subscription IDs (for recurring items)
     * @return Order
     */
    private function createOrderFromCheckout(
        Customer $customer,
        array $items,
        ?string $sessionId,
        array $subscriptionIds = []
    ): Order {
        return DB::transaction(function () use ($customer, $items, $sessionId, $subscriptionIds) {
            $totalAmount = array_sum(array_column($items, 'price'));

            // Determine fulfilment status based on item types present
            $fulfilmentStatus = $this->determineFulfilmentStatus($items);

            $order = Order::create([
                'company_id' => $customer->company_id,
                'payment_status' => 'pending',
                'fulfilment_status' => $fulfilmentStatus,
                'stripe_checkout_session_id' => $sessionId,
                'total_amount' => $totalAmount,
            ]);

            // Track which recurring subscription index we're on
            $subscriptionIndex = 0;

            foreach ($items as $item) {
                $orderItemData = [
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['name'],
                    'product_type' => $item['product_type'],
                    'price' => $item['price'],
                    'billing_frequency' => $item['billing_frequency'] ?? null,
                ];

                // Link subscription ID to recurring items
                if (in_array($item['product_type'], ['equipment_rental', 'hosting'], true)) {
                    if (isset($subscriptionIds[$subscriptionIndex])) {
                        $orderItemData['stripe_subscription_id'] = $subscriptionIds[$subscriptionIndex];
                        $subscriptionIndex++;
                    }
                }

                OrderItem::create($orderItemData);
            }

            return $order;
        });
    }

    /**
     * Determine the initial fulfilment status based on the types of items in the order.
     *
     * - One-off only → 'pending' (awaiting admin fulfilment after payment)
     * - Equipment rental present → 'awaiting_fulfilment'
     * - Hosting only (no equipment rental, no one-off) → 'completed' (auto-provisioned)
     * - Mixed → 'awaiting_fulfilment' (most restrictive pending state)
     */
    private function determineFulfilmentStatus(array $items): string
    {
        $types = array_unique(array_column($items, 'product_type'));

        if (in_array('equipment_rental', $types, true)) {
            return 'awaiting_fulfilment';
        }

        if (in_array('one_off', $types, true)) {
            return 'pending';
        }

        // Hosting only
        return 'completed';
    }
}
