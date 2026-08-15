<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
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
     */
    public function show(Product $product): View
    {
        $customer = Customer::find(auth()->user()->company_id);

        // Ensure the authenticated customer can see this product
        $visible = Product::visible($customer)->where('products.id', $product->id)->exists();
        abort_unless($visible, 404);

        return view('portal.shop.show', compact('product'));
    }
}
