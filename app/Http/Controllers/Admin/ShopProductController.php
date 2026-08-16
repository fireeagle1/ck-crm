<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\CustomerTier;
use App\Models\Product;
use App\Models\ProductVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Mews\Purifier\Facades\Purifier;

class ShopProductController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::query();

        if ($request->filled('product_type')) {
            $query->where('product_type', $request->input('product_type'));
        }

        if ($request->filled('archived')) {
            $archived = $request->input('archived');
            if ($archived === '1') {
                $query->where('is_archived', true);
            } elseif ($archived === '0') {
                $query->where('is_archived', false);
            }
        }

        $products = $query->orderByDesc('created_at')->paginate(20);

        return view('admin.shop.products.index', compact('products'));
    }

    public function create(): View
    {
        $customers = Customer::orderBy('company_name')->get();
        $tiers = CustomerTier::orderBy('name')->get();

        return view('admin.shop.products.create', compact('customers', 'tiers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'product_type' => 'required|in:equipment_rental,one_off,hosting',
            'price' => 'required|numeric|min:0.01',
            'billing_frequency' => 'required_if:product_type,hosting|nullable|in:monthly,quarterly,annually',
            'stock_quantity' => 'nullable|integer|min:0',
            'image' => 'nullable|image|max:2048',
            'visibility_type' => 'nullable|in:all,customers,tiers',
            'visibility_customers' => 'nullable|array',
            'visibility_customers.*' => 'exists:customers,company_id',
            'visibility_tiers' => 'nullable|array',
            'visibility_tiers.*' => 'exists:customer_tiers,id',
            'min_rental_days' => 'nullable|integer|min:1',
            'cooldown_days' => 'nullable|integer|min:0',
            'rental_agreement_text' => 'nullable|string',
            'delivery_instructions' => 'nullable|string',
            'delivery_charge' => 'nullable|numeric|min:0',
            'low_stock_threshold' => 'nullable|integer|min:1',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product = Product::create([
            'name' => $validated['name'],
            'description' => Purifier::clean($validated['description']),
            'product_type' => $validated['product_type'],
            'price' => $validated['price'],
            'billing_frequency' => $validated['billing_frequency'] ?? null,
            'stock_quantity' => $validated['stock_quantity'] ?? null,
            'image_path' => $imagePath,
            'is_archived' => false,
            'min_rental_days' => $validated['min_rental_days'] ?? null,
            'cooldown_days' => $validated['cooldown_days'] ?? null,
            'rental_agreement_text' => isset($validated['rental_agreement_text']) ? Purifier::clean($validated['rental_agreement_text']) : null,
            'delivery_instructions' => $validated['delivery_instructions'] ?? null,
            'delivery_charge' => $validated['delivery_charge'] ?? null,
            'low_stock_threshold' => $validated['low_stock_threshold'] ?? null,
        ]);

        $this->syncVisibility($product, $validated);

        return redirect()->route('admin.shop.products.index')
            ->with('success', 'Product created successfully.');
    }

    public function edit(Product $product): View
    {
        $product->load('visibilityRule.customers', 'visibilityRule.tiers');
        $customers = Customer::orderBy('company_name')->get();
        $tiers = CustomerTier::orderBy('name')->get();

        $selectedCustomerIds = $product->visibilityRule?->customers->pluck('company_id')->toArray() ?? [];
        $selectedTierIds = $product->visibilityRule?->tiers->pluck('id')->toArray() ?? [];

        // Load linked assets data for equipment_rental products
        $linkedAssets = collect();
        $availableAssetCount = 0;
        $totalAssetCount = 0;

        if ($product->isEquipmentRental()) {
            $linkedAssets = $product->assets()->orderBy('device_name')->get();
            $totalAssetCount = $linkedAssets->count();
            $availableAssetCount = $product->getAvailableAssetCount();
        }

        return view('admin.shop.products.edit', compact(
            'product',
            'customers',
            'tiers',
            'selectedCustomerIds',
            'selectedTierIds',
            'linkedAssets',
            'availableAssetCount',
            'totalAssetCount'
        ));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'product_type' => 'required|in:equipment_rental,one_off,hosting',
            'price' => 'required|numeric|min:0.01',
            'billing_frequency' => 'required_if:product_type,hosting|nullable|in:monthly,quarterly,annually',
            'stock_quantity' => 'nullable|integer|min:0',
            'image' => 'nullable|image|max:2048',
            'visibility_type' => 'nullable|in:all,customers,tiers',
            'visibility_customers' => 'nullable|array',
            'visibility_customers.*' => 'exists:customers,company_id',
            'visibility_tiers' => 'nullable|array',
            'visibility_tiers.*' => 'exists:customer_tiers,id',
            'min_rental_days' => 'nullable|integer|min:1',
            'cooldown_days' => 'nullable|integer|min:0',
            'rental_agreement_text' => 'nullable|string',
            'delivery_instructions' => 'nullable|string',
            'delivery_charge' => 'nullable|numeric|min:0',
            'low_stock_threshold' => 'nullable|integer|min:1',
        ]);

        $imagePath = $product->image_path;
        if ($request->hasFile('image')) {
            // Delete old image if it exists
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product->update([
            'name' => $validated['name'],
            'description' => Purifier::clean($validated['description']),
            'product_type' => $validated['product_type'],
            'price' => $validated['price'],
            'billing_frequency' => $validated['billing_frequency'] ?? null,
            'stock_quantity' => $validated['stock_quantity'] ?? null,
            'image_path' => $imagePath,
            'min_rental_days' => $validated['min_rental_days'] ?? null,
            'cooldown_days' => $validated['cooldown_days'] ?? null,
            'rental_agreement_text' => isset($validated['rental_agreement_text']) ? Purifier::clean($validated['rental_agreement_text']) : null,
            'delivery_instructions' => $validated['delivery_instructions'] ?? null,
            'delivery_charge' => $validated['delivery_charge'] ?? null,
            'low_stock_threshold' => $validated['low_stock_threshold'] ?? null,
        ]);

        $this->syncVisibility($product, $validated);

        return redirect()->route('admin.shop.products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function archive(Product $product): RedirectResponse
    {
        $product->update(['is_archived' => true]);

        return redirect()->route('admin.shop.products.index')
            ->with('success', "Product '{$product->name}' archived.");
    }

    public function restore(Product $product): RedirectResponse
    {
        $product->update(['is_archived' => false]);

        return redirect()->route('admin.shop.products.index')
            ->with('success', "Product '{$product->name}' restored.");
    }

    /**
     * Link an existing asset to an equipment_rental product.
     */
    public function linkAsset(Request $request, Product $product): RedirectResponse
    {
        if (!$product->isEquipmentRental()) {
            return redirect()->route('admin.shop.products.edit', $product)
                ->with('error', 'Only equipment rental products support asset linking.');
        }

        $validated = $request->validate([
            'asset_id' => 'required|exists:cmdb,device_id',
        ]);

        $asset = Asset::where('device_id', $validated['asset_id'])->firstOrFail();

        if ($asset->product_id !== null && $asset->product_id !== $product->id) {
            return redirect()->route('admin.shop.products.edit', $product)
                ->with('error', 'This asset is already linked to another product.');
        }

        $asset->update(['product_id' => $product->id]);

        return redirect()->route('admin.shop.products.edit', $product)
            ->with('success', "Asset '{$asset->device_name}' linked successfully.");
    }

    /**
     * Unlink an asset from an equipment_rental product.
     */
    public function unlinkAsset(Product $product, Asset $asset): RedirectResponse
    {
        if ($asset->product_id !== $product->id) {
            return redirect()->route('admin.shop.products.edit', $product)
                ->with('error', 'This asset is not linked to this product.');
        }

        $asset->update(['product_id' => null]);

        return redirect()->route('admin.shop.products.edit', $product)
            ->with('success', "Asset '{$asset->device_name}' unlinked successfully.");
    }

    /**
     * Sync the product's visibility rule and associated pivot entries.
     */
    private function syncVisibility(Product $product, array $validated): void
    {
        $visibilityType = $validated['visibility_type'] ?? 'all';

        $visibility = $product->visibilityRule()->updateOrCreate(
            ['product_id' => $product->id],
            ['visibility_type' => $visibilityType]
        );

        // Sync customer pivot
        if ($visibilityType === 'customers' && !empty($validated['visibility_customers'])) {
            $visibility->customers()->sync($validated['visibility_customers']);
        } else {
            $visibility->customers()->detach();
        }

        // Sync tier pivot
        if ($visibilityType === 'tiers' && !empty($validated['visibility_tiers'])) {
            $visibility->tiers()->sync($validated['visibility_tiers']);
        } else {
            $visibility->tiers()->detach();
        }
    }
}
