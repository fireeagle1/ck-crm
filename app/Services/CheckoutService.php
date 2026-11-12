<?php

namespace App\Services;

use App\Exceptions\BookingConflictException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\QuestionAnswer;
use App\Support\DomainNormaliser;
use App\Support\StripeCreated;
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
        private AssetAssignmentService $assetAssignmentService,
        private StockService $stockService,
        private HostingServiceCreator $hostingServiceCreator,
        private OrderCancellationService $orderCancellationService,
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
        // Declared outside the try so failure compensation (task 4.11) can see what exists.
        $order = null;
        $created = new StripeCreated();

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

            // Ensure the customer has a Stripe customer record (outside any transaction)
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

            // Transaction 1: local state only. No Stripe call happens inside it.
            [$order, $orderItems] = DB::transaction(function () use ($customer, $cartItems, $deliveryAddress, $checkoutOptions, $requiresAddress, $deliveryMethod, $deliveryCharge, $discountCode, $discountAmount) {
                $itemsTotal = $this->calculateTotalAmount($cartItems);
                $totalAmount = max(0, round($itemsTotal + $deliveryCharge - $discountAmount, 2));

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

                if ($requiresAddress && !empty($deliveryAddress) && $deliveryMethod === 'delivery') {
                    $orderData['delivery_address_line1'] = $deliveryAddress['address_line1'] ?? null;
                    $orderData['delivery_address_line2'] = $deliveryAddress['address_line2'] ?? null;
                    $orderData['delivery_city'] = $deliveryAddress['city'] ?? null;
                    $orderData['delivery_state'] = $deliveryAddress['state'] ?? null;
                    $orderData['delivery_postal_code'] = $deliveryAddress['postal_code'] ?? null;
                    $orderData['delivery_country'] = $deliveryAddress['country'] ?? null;
                }

                $order = Order::create($orderData);

                $orderItems = $this->createOrderItems($order, $cartItems);

                // Store question answers for each order item
                $answers = $checkoutOptions['answers'] ?? [];
                foreach ($cartItems as $index => $item) {
                    $productAnswers = $answers[$item['product_id']] ?? [];
                    if (!empty($productAnswers)) {
                        $product = Product::with('questions')->find($item['product_id']);
                        if ($product) {
                            $this->storeQuestionAnswers($orderItems[$index], $productAnswers, $product);
                        }
                    }
                }

                // Hosting items: pending Services through the single creator
                $this->handleHostingItems($orderItems, $cartItems, $customer);

                // Rental items: Bookings with pessimistic locking
                $this->handleRentalItems($order, $orderItems, $cartItems, $checkoutOptions);

                // One-off items: reserve stock through the shared service
                $this->reserveStockForOneOffItems($orderItems, $cartItems);

                return [$order, $orderItems];
            });

            // Transaction 1 has committed. Stripe calls follow, outside any transaction.
            // $created is filled in as each object is created so it can be compensated.
            try {
                $stripeResult = $this->runStripeSteps($customer, $order, $cartItems, $orderItems, $deliveryCharge, $discountAmount, $created);
            } catch (\Throwable $e) {
                return $this->compensateStripeFailure($customer, $order, $created, $e);
            }

            $result = [
                'order' => $order,
                'checkoutSessionUrl' => $stripeResult['checkoutSessionUrl'],
                'subscriptionIds' => $stripeResult['subscriptionIds'],
            ];

            $order = $result['order'];

            // Notify admin of new order (outside transaction so order is persisted)
            $this->notificationService->notifyAdminNewOrder($order);

            // Send booking confirmed emails for any rental items (outside transaction)
            $order->loadMissing('items.booking');
            foreach ($order->items as $item) {
                if ($item->booking) {
                    $this->notificationService->notifyCustomerBookingConfirmed($item->booking);
                }
            }

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
     * A Stripe step failed after the order was committed: fail the order (restores stock,
     * removes Pending services, cancels bookings) and cancel any Stripe objects already created.
     */
    private function compensateStripeFailure(Customer $customer, Order $order, StripeCreated $created, \Throwable $e): CheckoutResult
    {
        Log::error('Checkout failed after commit: Stripe error, failing order', [
            'company_id' => $customer->company_id,
            'order_id' => $order->id,
            'error' => $e->getMessage(),
        ]);

        try {
            $this->orderCancellationService->failOrder($order, $created);
        } catch (\Throwable $inner) {
            Log::critical('Checkout compensation failed; order needs manual cleanup', [
                'order_id' => $order->id,
                'error' => $inner->getMessage(),
            ]);
        }

        return new CheckoutResult(
            checkoutSessionUrl: null,
            order: null,
            subscriptionIds: [],
            success: false,
            errorMessage: $e->getMessage(),
        );
    }

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
     * Reserve stock for one-off items through StockService (caller owns the transaction).
     *
     * @param array<int, OrderItem> $orderItems
     * @throws \InvalidArgumentException If stock is insufficient for any product.
     */
    private function reserveStockForOneOffItems(array $orderItems, array $cartItems): void
    {
        foreach ($cartItems as $index => $item) {
            if (($item['product_type'] ?? '') !== 'one_off') {
                continue;
            }

            $product = Product::find($item['product_id']);

            if (!$product) {
                continue;
            }

            $this->stockService->reserve($product, (int) ($item['quantity'] ?? 1), $orderItems[$index]);
        }
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
                'domain_name' => DomainNormaliser::tryNormalise($item['domain_name'] ?? null),
                'rental_start_date' => $item['rental_start_date'] ?? null,
                'rental_end_date' => $item['rental_end_date'] ?? null,
            ];

            $orderItems[$index] = OrderItem::create($orderItemData);
        }

        return $orderItems;
    }

    /**
     * Handle hosting items: create pending Services through HostingServiceCreator.
     *
     * @param array<int, OrderItem> $orderItems
     */
    private function handleHostingItems(array $orderItems, array $cartItems, Customer $customer): void
    {
        foreach ($cartItems as $index => $item) {
            if ($item['product_type'] !== 'hosting') {
                continue;
            }

            $this->hostingServiceCreator->forOrderItem($orderItems[$index], $customer);
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

            // Auto-assign assets from CMDB pool if product tracks individual assets
            $this->assetAssignmentService->autoAssign($booking);

            // Generate booking confirmation PDF
            app(BookingConfirmationPdfService::class)->generate($booking);

            $orderItem->update(['booking_id' => $booking->id]);
        }
    }

    /**
     * Run the post-commit Stripe steps in order: coupon, Checkout Session, subscriptions.
     *
     * Every created object id is recorded in $created (by reference) the moment it exists,
     * so a failure part-way through still leaves an accurate record for compensation.
     *
     * @param array<int, OrderItem> $orderItems
     * @return array{checkoutSessionUrl: ?string, subscriptionIds: list<string>}
     */
    private function runStripeSteps(
        Customer $customer,
        Order $order,
        array $cartItems,
        array $orderItems,
        float $deliveryCharge,
        float $discountAmount,
        StripeCreated &$created,
    ): array {
        $lineItems = $this->buildSessionLineItems($cartItems, $deliveryCharge);

        $checkoutSessionUrl = null;

        if (!empty($lineItems)) {
            $sessionParams = [
                'customer' => $customer->stripe_customer_id,
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => route('portal.orders.index') . '?checkout=success',
                'cancel_url' => route('portal.cart.index') . '?checkout=cancelled',
                'client_reference_id' => (string) $order->id,
                'metadata' => ['order_id' => (string) $order->id],
            ];

            if ($discountAmount > 0) {
                $coupon = $this->stripe->createDiscountCoupon([
                    'amount_off' => (int) round($discountAmount * 100),
                    'currency' => $this->currency(),
                    'duration' => 'once',
                    'name' => 'Discount',
                ]);
                $created = $created->withCoupon($coupon->id);
                $sessionParams['discounts'] = [['coupon' => $coupon->id]];
            }

            $session = $this->stripe->createCheckoutSessionWithParams($sessionParams);
            $created = $created->withSession($session->id);
            $order->update(['stripe_checkout_session_id' => $session->id]);
            $checkoutSessionUrl = $session->url;
        }

        $subscriptionIds = [];

        foreach ($cartItems as $index => $item) {
            if ($item['product_type'] !== 'hosting') {
                continue;
            }

            $orderItem = $orderItems[$index];
            $priceId = $item['stripe_price_id'] ?? Product::find($item['product_id'])?->stripe_price_id ?? 'price_hosting_' . $item['product_id'];

            $metadata = [
                'product_id' => $item['product_id'],
                'product_name' => $item['name'],
                'product_type' => $item['product_type'],
                'company_id' => $customer->company_id,
                'order_id' => (string) $order->id,
            ];

            if (!empty($orderItem->domain_name)) {
                $metadata['domain_name'] = $orderItem->domain_name;
            }

            $subscription = $this->stripe->createSubscription(
                $customer->stripe_customer_id,
                $priceId,
                $metadata,
            );

            $created = $created->withSubscription($subscription->id);
            $subscriptionIds[] = $subscription->id;
            $orderItem->update(['stripe_subscription_id' => $subscription->id]);
        }

        return [
            'checkoutSessionUrl' => $checkoutSessionUrl,
            'subscriptionIds' => $subscriptionIds,
        ];
    }

    /**
     * Line items for the Checkout Session: one-off, equipment rental and delivery.
     */
    private function buildSessionLineItems(array $cartItems, float $deliveryCharge): array
    {
        $currency = $this->currency();
        $lineItems = [];

        foreach ($cartItems as $item) {
            if ($item['product_type'] === 'one_off') {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => ['name' => $item['name']],
                        'unit_amount' => (int) round($item['price'] * 100),
                    ],
                    'quantity' => $item['quantity'] ?? 1,
                ];
            } elseif ($item['product_type'] === 'equipment_rental') {
                $totalPrice = $item['total_price'] ?? $item['price'];
                $lineItems[] = [
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => ['name' => $item['name'] . ' (Rental)'],
                        'unit_amount' => (int) round($totalPrice * 100),
                    ],
                    'quantity' => 1,
                ];
            }
        }

        if ($deliveryCharge > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => ['name' => 'Delivery Charge'],
                    'unit_amount' => (int) round($deliveryCharge * 100),
                ],
                'quantity' => 1,
            ];
        }

        return $lineItems;
    }

    /**
     * The single place CheckoutService reads the Stripe currency (Req 5.6).
     */
    private function currency(): string
    {
        return (string) (config('services.stripe.currency', 'gbp') ?: 'gbp');
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

    /**
     * Store question answers for an order item, snapshotting question label and type.
     *
     * Skips storage entirely if the product has no questions configured.
     * Skips individual answers that are optional and empty.
     *
     * @param OrderItem $orderItem The order item to attach answers to.
     * @param array $answers Associative array of question_id => answer_value.
     * @param Product $product The product (with questions loaded).
     */
    private function storeQuestionAnswers(OrderItem $orderItem, array $answers, Product $product): void
    {
        if ($product->questions->isEmpty()) {
            return;
        }

        foreach ($product->questions as $question) {
            $value = $answers[$question->id] ?? null;

            // Skip if optional and empty
            if (!$question->is_required && empty($value)) {
                continue;
            }

            QuestionAnswer::create([
                'order_item_id' => $orderItem->id,
                'product_question_id' => $question->id,
                'answer_value' => $value,
                'question_label' => $question->label,
                'question_type' => $question->input_type,
            ]);
        }
    }
}
