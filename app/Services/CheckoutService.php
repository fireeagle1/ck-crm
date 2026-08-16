<?php

namespace App\Services;

use App\Exceptions\BookingConflictException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\ValueObjects\CheckoutResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutService
{
    public function __construct(
        private StripeService $stripe,
        private BookingService $bookingService,
        private NotificationService $notificationService,
    ) {}

    /**
     * Process the full checkout flow for the given customer and cart.
     *
     * Creates an Order with delivery address, creates OrderItems with rental dates,
     * domain names, and quantities. Routes one-off items to Stripe Checkout Session,
     * hosting items to Service creation with 'pending' status, and equipment rental
     * items to BookingService for booking creation. Notifies admin and clears cart.
     *
     * @param Customer $customer The customer placing the order.
     * @param CartService $cart The cart service with items to checkout.
     * @param array $deliveryAddress Address fields: address_line1, address_line2, city, state, postal_code, country.
     * @param array $checkoutOptions Optional: rental_agreements, signatures, delivery_method, discount_code, discount_amount.
     * @return CheckoutResult
     */
    public function processCheckout(
        Customer $customer,
        CartService $cart,
        array $deliveryAddress = [],
        array $checkoutOptions = []
    ): CheckoutResult {
        try {
            $cartItems = $cart->getItems();

            if (empty($cartItems)) {
                return new CheckoutResult(
                    checkoutSessionUrl: null,
                    order: null,
                    subscriptionIds: [],
                    success: true,
                    errorMessage: null,
                );
            }

            // Ensure the customer has a Stripe customer record
            $this->stripe->ensureCustomer($customer);

            // Determine if address is needed (skip for hosting-only carts)
            $requiresAddress = !$cart->hasOnlyHostingItems();

            // Validate delivery address if required
            if ($requiresAddress && !empty($deliveryAddress)) {
                $this->validateDeliveryAddress($deliveryAddress);
            }

            // Determine delivery method and charge
            $deliveryMethod = $checkoutOptions['delivery_method'] ?? 'delivery';
            $deliveryCharge = $cart->getDeliveryTotal($deliveryMethod);

            // Determine discount
            $discountCode = $checkoutOptions['discount_code'] ?? null;
            $discountAmount = (float) ($checkoutOptions['discount_amount'] ?? 0);

            // Create Order and OrderItems within a transaction, then handle payments
            $result = DB::transaction(function () use ($customer, $cartItems, $deliveryAddress, $checkoutOptions, $requiresAddress, $deliveryMethod, $deliveryCharge, $discountCode, $discountAmount) {
                // Calculate total amount (items + delivery - discount)
                $itemsTotal = $this->calculateTotalAmount($cartItems);
                $totalAmount = round($itemsTotal + $deliveryCharge - $discountAmount, 2);
                $totalAmount = max(0, $totalAmount);

                // Create the Order
                $orderData = [
                    'company_id' => $customer->company_id,
                    'payment_status' => 'pending',
                    'fulfilment_status' => $this->determineFulfilmentStatus($cartItems),
                    'total_amount' => $totalAmount,
                    'delivery_method' => $deliveryMethod,
                    'delivery_charge' => $deliveryCharge,
                    'discount_code' => $discountCode,
                    'discount_amount' => $discountAmount,
                ];

                // Add delivery address if provided and required
                if ($requiresAddress && !empty($deliveryAddress) && $deliveryMethod === 'delivery') {
                    $orderData['delivery_address_line1'] = $deliveryAddress['address_line1'] ?? null;
                    $orderData['delivery_address_line2'] = $deliveryAddress['address_line2'] ?? null;
                    $orderData['delivery_city'] = $deliveryAddress['city'] ?? null;
                    $orderData['delivery_state'] = $deliveryAddress['state'] ?? null;
                    $orderData['delivery_postal_code'] = $deliveryAddress['postal_code'] ?? null;
                    $orderData['delivery_country'] = $deliveryAddress['country'] ?? null;
                }

                $order = Order::create($orderData);

                // Create OrderItems
                $orderItems = $this->createOrderItems($order, $cartItems);

                // Handle hosting items: create pending Services
                $this->handleHostingItems($order, $orderItems, $cartItems, $customer);

                // Handle rental items: create Bookings with pessimistic locking
                $this->handleRentalItems($order, $orderItems, $cartItems, $checkoutOptions);

                // Handle Stripe: create Checkout Session for one-off + rental items
                $checkoutSessionUrl = $this->createStripeCheckoutSession($customer, $order, $cartItems, $orderItems, $deliveryCharge, $discountAmount);

                // Store checkout session ID on the order
                if ($this->lastCheckoutSessionId) {
                    $order->update(['stripe_checkout_session_id' => $this->lastCheckoutSessionId]);
                }

                // Handle recurring/hosting subscriptions
                $subscriptionIds = $this->processHostingSubscriptions($customer, $cartItems, $orderItems);

                return [
                    'order' => $order,
                    'checkoutSessionUrl' => $checkoutSessionUrl,
                    'subscriptionIds' => $subscriptionIds,
                ];
            });

            $order = $result['order'];

            // Notify admin of new order (outside transaction so order is persisted)
            $this->notificationService->notifyAdminNewOrder($order);

            // Clear cart on successful checkout
            $cart->clear();

            return new CheckoutResult(
                checkoutSessionUrl: $result['checkoutSessionUrl'],
                order: $order,
                subscriptionIds: $result['subscriptionIds'],
                success: true,
                errorMessage: null,
            );
        } catch (BookingConflictException $e) {
            Log::warning('Checkout failed: booking conflict', [
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
        } catch (\Throwable $e) {
            Log::error('Checkout failed', [
                'company_id' => $customer->company_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
     * Legacy method for backward compatibility.
     * Processes checkout from raw cart items array (used by existing tests).
     *
     * @param Customer $customer
     * @param array<int, array{product_id: int, name: string, price: float, product_type: string, billing_frequency: ?string}> $cartItems
     * @return CheckoutResult
     */
    public function processCheckoutFromArray(Customer $customer, array $cartItems): CheckoutResult
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
            $checkoutSessionUrl = $this->processOneOffItemsLegacy($customer, $oneOffItems);
            $subscriptionIds = $this->processRecurringItemsLegacy($customer, $recurringItems);

            // Determine the stripe_checkout_session_id for the order
            $sessionId = $this->lastCheckoutSessionId;

            // Create Order and OrderItems within a transaction
            $order = $this->createOrderFromCheckoutLegacy($customer, $cartItems, $sessionId, $subscriptionIds);

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
     * Validate the delivery address has all required fields.
     *
     * @throws \InvalidArgumentException if required fields are missing.
     */
    private function validateDeliveryAddress(array $address): void
    {
        $required = ['address_line1', 'city', 'postal_code', 'country'];

        $missing = [];
        foreach ($required as $field) {
            if (empty($address[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new \InvalidArgumentException(
                'Missing required delivery address fields: ' . implode(', ', $missing)
            );
        }
    }

    /**
     * Calculate the total amount for all cart items.
     */
    private function calculateTotalAmount(array $cartItems): float
    {
        $total = 0.0;

        foreach ($cartItems as $item) {
            $total += $item['total_price'] ?? $item['price'];
        }

        return round($total, 2);
    }

    /**
     * Create OrderItem records for each cart item.
     *
     * @return array<int, OrderItem> Indexed by cart item position.
     */
    private function createOrderItems(Order $order, array $cartItems): array
    {
        $orderItems = [];

        foreach ($cartItems as $index => $item) {
            $orderItemData = [
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'product_name' => $item['name'],
                'product_type' => $item['product_type'],
                'price' => $item['price'],
                'billing_frequency' => $item['billing_frequency'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'domain_name' => $item['domain_name'] ?? null,
                'rental_start_date' => $item['rental_start_date'] ?? null,
                'rental_end_date' => $item['rental_end_date'] ?? null,
            ];

            $orderItems[$index] = OrderItem::create($orderItemData);
        }

        return $orderItems;
    }

    /**
     * Handle hosting items: create pending Service records.
     */
    private function handleHostingItems(Order $order, array $orderItems, array $cartItems, Customer $customer): void
    {
        foreach ($cartItems as $index => $item) {
            if ($item['product_type'] !== 'hosting') {
                continue;
            }

            $orderItem = $orderItems[$index];

            $service = Service::create([
                'company_id' => $customer->company_id,
                'service_short' => $item['name'],
                'service_type' => 'hosting',
                'status' => 'pending',
                'domain_name' => $item['domain_name'] ?? null,
                'start_date' => now(),
                'service_monthly_charge' => $item['price'],
                'service_payment_frequency' => $item['billing_frequency'] ?? null,
            ]);

            $orderItem->update(['service_id' => $service->service_id]);
        }
    }

    /**
     * Handle rental items: create Bookings via BookingService with pessimistic locking.
     *
     * @throws BookingConflictException if dates are no longer available.
     */
    private function handleRentalItems(Order $order, array $orderItems, array $cartItems, array $checkoutOptions): void
    {
        $signatures = $checkoutOptions['signatures'] ?? [];
        $agreements = $checkoutOptions['rental_agreements'] ?? [];

        foreach ($cartItems as $index => $item) {
            if ($item['product_type'] !== 'equipment_rental') {
                continue;
            }

            // Rental items must have dates
            if (empty($item['rental_start_date']) || empty($item['rental_end_date'])) {
                continue;
            }

            $orderItem = $orderItems[$index];
            $product = Product::find($item['product_id']);

            if (!$product) {
                continue;
            }

            $startDate = Carbon::parse($item['rental_start_date']);
            $endDate = Carbon::parse($item['rental_end_date']);
            $quantity = $item['quantity'] ?? 1;

            // Get signature and agreement text for this product
            $signatureData = $signatures[$item['product_id']] ?? null;
            $agreementText = $agreements[$item['product_id']] ?? null;

            $booking = $this->bookingService->createBooking(
                $orderItem,
                $product,
                $startDate,
                $endDate,
                $quantity,
                $signatureData,
                $agreementText
            );

            $orderItem->update(['booking_id' => $booking->id]);
        }
    }

    /**
     * Create a Stripe Checkout Session for one-off items and equipment rental items.
     * Equipment rental items are included as one-time charges in the session.
     * Includes delivery charge as a line item and applies discount.
     *
     * @return string|null The checkout session URL, or null if no payable items.
     */
    private function createStripeCheckoutSession(Customer $customer, Order $order, array $cartItems, array $orderItems, float $deliveryCharge = 0, float $discountAmount = 0): ?string
    {
        // Collect items that go through Stripe Checkout Session (one-off + equipment_rental)
        $lineItems = [];

        foreach ($cartItems as $index => $item) {
            if ($item['product_type'] === 'one_off') {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'gbp',
                        'product_data' => [
                            'name' => $item['name'],
                        ],
                        'unit_amount' => (int) round($item['price'] * 100),
                    ],
                    'quantity' => $item['quantity'] ?? 1,
                ];
            } elseif ($item['product_type'] === 'equipment_rental') {
                // Equipment rental total goes as a one-time charge
                $totalPrice = $item['total_price'] ?? $item['price'];
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'gbp',
                        'product_data' => [
                            'name' => $item['name'] . ' (Rental)',
                        ],
                        'unit_amount' => (int) round($totalPrice * 100),
                    ],
                    'quantity' => 1,
                ];
            }
        }

        // Add delivery charge as a line item
        if ($deliveryCharge > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'gbp',
                    'product_data' => [
                        'name' => 'Delivery Charge',
                    ],
                    'unit_amount' => (int) round($deliveryCharge * 100),
                ],
                'quantity' => 1,
            ];
        }

        if (empty($lineItems)) {
            $this->lastCheckoutSessionId = null;
            return null;
        }

        $successUrl = route('portal.orders.index') . '?checkout=success';
        $cancelUrl = route('portal.cart.index') . '?checkout=cancelled';

        // Build session params
        $sessionParams = [
            'customer' => $customer->stripe_customer_id,
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ];

        // Apply discount as a coupon if applicable
        if ($discountAmount > 0) {
            // Create an inline coupon for the discount amount
            try {
                $coupon = \Stripe\Coupon::create([
                    'amount_off' => (int) round($discountAmount * 100),
                    'currency' => 'gbp',
                    'duration' => 'once',
                    'name' => 'Discount',
                ]);
                $sessionParams['discounts'] = [['coupon' => $coupon->id]];
            } catch (\Throwable $e) {
                Log::warning('Failed to create Stripe coupon for discount, proceeding without', [
                    'discount_amount' => $discountAmount,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $session = $this->stripe->createCheckoutSessionWithParams($sessionParams);

        $this->lastCheckoutSessionId = $session->id;

        return $session->url;
    }

    /**
     * Process hosting items as Stripe subscriptions.
     *
     * @return array List of Stripe subscription IDs.
     */
    private function processHostingSubscriptions(Customer $customer, array $cartItems, array $orderItems): array
    {
        $subscriptionIds = [];

        foreach ($cartItems as $index => $item) {
            if ($item['product_type'] !== 'hosting') {
                continue;
            }

            $priceId = $item['stripe_price_id'] ?? 'price_hosting_' . $item['product_id'];

            $metadata = [
                'product_id' => $item['product_id'],
                'product_name' => $item['name'],
                'product_type' => $item['product_type'],
                'company_id' => $customer->company_id,
            ];

            if (!empty($item['domain_name'])) {
                $metadata['domain_name'] = $item['domain_name'];
            }

            $subscription = $this->stripe->createSubscription(
                $customer->stripe_customer_id,
                $priceId,
                $metadata,
            );

            $subscriptionIds[] = $subscription->id;

            // Update the order item with the subscription ID
            $orderItem = $orderItems[$index];
            $orderItem->update(['stripe_subscription_id' => $subscription->id]);
        }

        return $subscriptionIds;
    }

    /**
     * Determine the initial fulfilment status based on item types in the order.
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

    // ─── Legacy Methods (backward compatibility with existing tests) ─────

    /**
     * Process one-off items by creating a Stripe Checkout Session (legacy).
     */
    private function processOneOffItemsLegacy(Customer $customer, array $items): ?string
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
                    'unit_amount' => (int) round($item['price'] * 100),
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
     * Process recurring items by creating Stripe subscriptions (legacy).
     */
    private function processRecurringItemsLegacy(Customer $customer, array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $subscriptionIds = [];

        foreach ($items as $item) {
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
     * Create Order and OrderItem records from checkout (legacy).
     */
    private function createOrderFromCheckoutLegacy(
        Customer $customer,
        array $items,
        ?string $sessionId,
        array $subscriptionIds = []
    ): Order {
        return DB::transaction(function () use ($customer, $items, $sessionId, $subscriptionIds) {
            $totalAmount = array_sum(array_column($items, 'price'));

            $fulfilmentStatus = $this->determineFulfilmentStatus($items);

            $order = Order::create([
                'company_id' => $customer->company_id,
                'payment_status' => 'pending',
                'fulfilment_status' => $fulfilmentStatus,
                'stripe_checkout_session_id' => $sessionId,
                'total_amount' => $totalAmount,
            ]);

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
}
