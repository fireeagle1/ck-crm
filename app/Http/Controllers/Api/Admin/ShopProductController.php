<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopProductController extends Controller
{
    /**
     * Paginated list of shop products with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);

        $query = Product::query();

        // Filter by product type
        if ($type = $request->input('product_type')) {
            $query->where('product_type', $type);
        }

        // Filter by archived status (default: show active only)
        if ($request->input('show_archived') === 'true') {
            // Show all
        } else {
            $query->where('is_archived', false);
        }

        $products = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'product_type' => $product->product_type,
                'price' => (float) $product->price,
                'billing_frequency' => $product->billing_frequency,
                'stock_quantity' => $product->stock_quantity,
                'is_archived' => $product->is_archived,
                'is_available' => $product->isAvailable(),
            ]),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }
}
