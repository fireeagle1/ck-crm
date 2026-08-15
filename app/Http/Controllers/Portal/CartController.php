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
     */
    public function add(Request $request, Product $product): RedirectResponse
    {
        $customer = Customer::find($request->user()->company_id);

        // Verify product is visible to this customer
        $visible = Product::visible($customer)->where('products.id', $product->id)->exists();
        abort_unless($visible, 404);

        try {
            $this->cartService->addItem($product);

            return redirect()->route('portal.shop.show', $product)
                ->with('success', "'{$product->name}' added to cart.");
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
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
     * Process checkout: call CheckoutService, handle success/failure.
     */
    public function checkout(Request $request): RedirectResponse
    {
        if ($this->cartService->isEmpty()) {
            return redirect()->route('portal.cart.index')
                ->with('error', 'Your cart is empty.');
        }

        $customer = Customer::find($request->user()->company_id);
        $result = $this->checkoutService->processCheckout($customer, $this->cartService->getItems());

        if ($result->success) {
            $this->cartService->clear();

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
