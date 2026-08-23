<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private CheckoutService $checkoutService
    ) {}

    /**
     * Display cart items with individual prices and total amount.
     */
    public function index(): View
    {
        $items = $this->cartService->getItems();
        $total = $this->cartService->getTotal();

        return view('portal.shop.cart', compact('items', 'total'));
    }

    /**
     * Add a product to the cart after verifying availability and visibility.
     *
     * Accepts rental options (rental_start_date, rental_end_date, quantity) for equipment_rental products,
     * quantity for one-off products, and domain_name for hosting products.
     * These are passed through to CartService for validation.
     */
    public function add(Request $request, Product $product): RedirectResponse
    {
        $customer = Customer::find($request->user()->company_id);

        // Verify product is visible to this customer
        $visible = Product::visible($customer)->where('products.id', $product->id)->exists();
        abort_unless($visible, 404);

        // Build options array from form submission based on product type
        $options = [];

        if ($product->isEquipmentRental()) {
            $options['rental_start_date'] = $request->input('rental_start_date');
            $options['rental_end_date'] = $request->input('rental_end_date');
            $options['quantity'] = $request->input('quantity', 1);
        }

        if ($product->isOneOff()) {
            $options['quantity'] = (int) $request->input('quantity', 1);
        }

        if ($product->isHosting()) {
            $options['domain_name'] = $request->input('domain_name');
        }

        try {
            $this->cartService->addItem($product, $options);

            return redirect()->route('portal.shop.show', $product)
                ->with('added_to_cart', $product->name);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove an item from the cart by its index.
     */
    public function remove(Request $request, int $index): RedirectResponse
    {
        $this->cartService->removeItem($index);

        return redirect()->route('portal.cart.index')
            ->with('success', 'Item removed from cart.');
    }

    /**
     * Update the quantity of a one-off cart item.
     */
    public function updateQuantity(Request $request, int $index): RedirectResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            $this->cartService->updateItemQuantity($index, (int) $request->input('quantity'));

            return redirect()->route('portal.cart.index')
                ->with('success', 'Quantity updated.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('portal.cart.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the checkout page with cart items, delivery address form,
     * rental agreement panels, and signature capture areas.
     */
    public function showCheckout(Request $request): View|RedirectResponse
    {
        if ($this->cartService->isEmpty()) {
            return redirect()->route('portal.cart.index')
                ->with('error', 'Your cart is empty.');
        }

        $customer = Customer::find($request->user()->company_id);
        $items = $this->cartService->getItems();
        $total = $this->cartService->getTotal();
        $hasPhysicalItems = $this->cartService->hasPhysicalItems();
        $deliveryTotal = $this->cartService->getDeliveryTotal('delivery');

        // Load product questions for each cart item
        $productIds = collect($items)->pluck('product_id')->unique()->toArray();
        $products = Product::with('questions')->whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($items as $index => $item) {
            $product = $products->get($item['product_id']);
            $items[$index]['questions'] = $product ? $product->questions : collect();
        }

        // Load product details for items that have rental agreements
        $rentalAgreements = [];
        foreach ($items as $index => $item) {
            if ($item['product_type'] === 'equipment_rental') {
                $product = $products->get($item['product_id']);
                if ($product && $product->hasRentalAgreement()) {
                    $rentalAgreements[$index] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'agreement_text' => $product->rental_agreement_text,
                    ];
                }
            }
        }

        return view('portal.shop.checkout', compact(
            'items',
            'total',
            'customer',
            'hasPhysicalItems',
            'rentalAgreements',
            'deliveryTotal'
        ));
    }

    /**
     * Process checkout: validate form data, call CheckoutService, handle success/failure.
     */
    public function checkout(Request $request): RedirectResponse
    {
        if ($this->cartService->isEmpty()) {
            return redirect()->route('portal.cart.index')
                ->with('error', 'Your cart is empty.');
        }

        $customer = Customer::find($request->user()->company_id);

        // Determine delivery method
        $deliveryMethod = $request->input('delivery_method', 'delivery');

        // Validate delivery address if delivery method is 'delivery' and cart has physical items
        if ($this->cartService->hasPhysicalItems() && $deliveryMethod === 'delivery' && $request->has('address_line1')) {
            $request->validate([
                'address_line1' => 'required|string|max:255',
                'city' => 'required|string|max:255',
                'postal_code' => 'required|string|max:20',
                'country' => 'required|string|max:100',
                'address_line2' => 'nullable|string|max:255',
                'state' => 'nullable|string|max:100',
            ]);
        }

        // Validate and apply discount code
        $discountCode = null;
        $discountAmount = 0;
        if ($request->filled('discount_code')) {
            $code = trim($request->input('discount_code'));
            $discountService = app(\App\Services\DiscountCodeService::class);
            $itemsTotal = $this->cartService->getTotal();
            $validation = $discountService->validate($code, $itemsTotal);

            if (!$validation['valid']) {
                return redirect()->route('portal.cart.showCheckout')
                    ->withInput()
                    ->with('error', $validation['message']);
            }

            $discountCode = $code;
            $discountAmount = $validation['discount_amount'];
        }

        // Validate rental agreement acceptance for items with agreements
        $items = $this->cartService->getItems();
        foreach ($items as $index => $item) {
            if ($item['product_type'] === 'equipment_rental') {
                $product = Product::find($item['product_id']);
                if ($product && $product->hasRentalAgreement()) {
                    if (!$request->input("rental_agreements.{$product->id}")) {
                        return redirect()->route('portal.cart.showCheckout')
                            ->with('error', "Please accept the rental agreement for {$product->name}.");
                    }
                    if (!$request->input("signatures.{$product->id}")) {
                        return redirect()->route('portal.cart.showCheckout')
                            ->with('error', "Please provide a signature for {$product->name}.");
                    }
                }
            }
        }

        // Validate question answers for products with configured questions
        $answerValidationRules = [];
        foreach ($items as $item) {
            $product = Product::with('questions')->find($item['product_id']);
            if ($product && $product->questions->isNotEmpty()) {
                foreach ($product->questions as $question) {
                    $answerKey = "answers.{$item['product_id']}.{$question->id}";
                    if ($question->is_required) {
                        $answerValidationRules[$answerKey] = 'required|string|min:1';
                    } else {
                        $answerValidationRules[$answerKey] = 'nullable|string';
                    }
                }
            }
        }
        if (!empty($answerValidationRules)) {
            $request->validate($answerValidationRules);
        }

        // Collect delivery address from request (if delivery method is 'delivery')
        $deliveryAddress = [];
        if ($deliveryMethod === 'delivery' && $request->has('address_line1')) {
            $deliveryAddress = [
                'address_line1' => $request->input('address_line1'),
                'address_line2' => $request->input('address_line2'),
                'city' => $request->input('city'),
                'state' => $request->input('state'),
                'postal_code' => $request->input('postal_code'),
                'country' => $request->input('country'),
            ];
        }

        // Collect checkout options (rental agreements, signatures, delivery method, discount)
        $checkoutOptions = [
            'delivery_method' => $deliveryMethod,
            'discount_code' => $discountCode,
            'discount_amount' => $discountAmount,
        ];
        if ($request->has('rental_agreements')) {
            $checkoutOptions['rental_agreements'] = $request->input('rental_agreements', []);
        }
        if ($request->has('signatures')) {
            $checkoutOptions['signatures'] = $request->input('signatures', []);
        }
        if ($request->has('answers')) {
            $checkoutOptions['answers'] = $request->input('answers', []);
        }

        $result = $this->checkoutService->processCheckout(
            $customer,
            $this->cartService,
            $deliveryAddress,
            $checkoutOptions
        );

        if ($result->success) {
            // Increment discount code usage if one was applied
            if ($discountCode) {
                $discountService = app(\App\Services\DiscountCodeService::class);
                $discountService->incrementUsage($discountCode);
            }

            // If there's a Stripe checkout session URL (one-off items), redirect to Stripe
            if ($result->checkoutSessionUrl) {
                return redirect()->away($result->checkoutSessionUrl);
            }

            // Otherwise (recurring-only orders), redirect to orders page
            return redirect()->route('portal.orders.index')
                ->with('success', 'Order placed successfully!');
        }

        // On failure, retain cart contents and show error
        return redirect()->route('portal.cart.index')
            ->with('error', $result->errorMessage ?? 'Checkout failed. Please try again.');
    }
}
