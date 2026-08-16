<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function __construct(
        private BookingService $bookingService
    ) {}

    /**
     * Display the shop product listing with optional type filter and search.
     */
    public function index(Request $request): View
    {
        $customer = Customer::find($request->user()->company_id);

        $query = Product::visible($customer);

        // Filter by product type
        if ($request->filled('type')) {
            $query->where('product_type', $request->input('type'));
        }

        // Search by name or description
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $products = $query->paginate(12);

        return view('portal.shop.index', compact('products'));
    }

    /**
     * Display a single product's detail page.
     *
     * For equipment_rental products, passes unavailable dates for the next 90 days,
     * minimum rental days, rental agreement text, and delivery instructions.
     * For hosting products, indicates domain name input is needed.
     */
    public function show(Product $product): View
    {
        $customer = Customer::find(auth()->user()->company_id);

        // Ensure the authenticated customer can see this product
        $visible = Product::visible($customer)->where('products.id', $product->id)->exists();
        abort_unless($visible, 404);

        $viewData = ['product' => $product];

        // For equipment_rental products, pass rental-specific data
        if ($product->isEquipmentRental()) {
            $rangeStart = Carbon::today();
            $rangeEnd = Carbon::today()->addDays(90);

            $unavailableDates = $this->bookingService
                ->getUnavailableDates($product, $rangeStart, $rangeEnd)
                ->map(fn (Carbon $date) => $date->format('Y-m-d'))
                ->values()
                ->toArray();

            $viewData['unavailableDates'] = json_encode($unavailableDates);
            $viewData['minRentalDays'] = $product->min_rental_days;
            $viewData['rentalAgreementText'] = $product->rental_agreement_text;
            $viewData['deliveryInstructions'] = $product->delivery_instructions;

            // Max quantity = count of linked assets if any exist, otherwise stock_quantity
            $linkedAssetCount = $product->assets()->count();
            if ($linkedAssetCount > 0) {
                // Product has CMDB assets linked — use total rentable assets as the cap
                $maxQuantity = $product->assets()
                    ->whereIn('asset_status', ['Available', 'Reserved', 'Rented Out'])
                    ->count();
            } else {
                $maxQuantity = $product->stock_quantity ?? 99;
            }
            $viewData['maxQuantity'] = max(1, (int) $maxQuantity);
        }

        // For one_off products, pass delivery instructions and max quantity
        if ($product->isOneOff()) {
            $viewData['deliveryInstructions'] = $product->delivery_instructions;
            $viewData['maxQuantity'] = $product->stock_quantity !== null
                ? min((int) $product->stock_quantity, 99)
                : 99;
        }

        return view('portal.shop.show', $viewData);
    }
}
