<x-admin-layout>
    <x-slot:title>Edit Product</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Edit Product</h1>
        <a href="{{ route('admin.shop.products.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Products</a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-lg border p-6 max-w-3xl" x-data="productEditForm()">
        <form method="POST" action="{{ route('admin.shop.products.update', $product) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="space-y-5">
                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-semibold text-gray-700">Product Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $product->name) }}" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Description --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Description <span class="text-red-500">*</span></label>
                    <x-rich-text-editor name="description" :value="old('description', $product->description)" placeholder="Describe the product..." :required="true" />
                    @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Product Type & Price --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="product_type" class="block text-sm font-semibold text-gray-700">Product Type <span class="text-red-500">*</span></label>
                        <select name="product_type" id="product_type" required x-model="productType"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="equipment_rental" {{ old('product_type', $product->product_type) === 'equipment_rental' ? 'selected' : '' }}>Equipment Rental</option>
                            <option value="one_off" {{ old('product_type', $product->product_type) === 'one_off' ? 'selected' : '' }}>One-Off Purchase</option>
                            <option value="hosting" {{ old('product_type', $product->product_type) === 'hosting' ? 'selected' : '' }}>Hosting</option>
                        </select>
                        @error('product_type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="price" class="block text-sm font-semibold text-gray-700">Price (&pound;) <span x-show="productType === 'equipment_rental'" class="text-xs font-normal text-gray-500">per day</span> <span class="text-red-500">*</span></label>
                        <input type="number" name="price" id="price" value="{{ old('price', $product->price) }}" required step="0.01" min="0.01"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('price') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Billing Frequency (shown for hosting only — rental uses per-day pricing) --}}
                <div x-show="productType === 'hosting'" x-transition>
                    <label for="billing_frequency" class="block text-sm font-semibold text-gray-700">Billing Frequency <span class="text-red-500">*</span></label>
                    <select name="billing_frequency" id="billing_frequency"
                            :required="productType === 'hosting'"
                            class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select frequency...</option>
                        <option value="monthly" {{ old('billing_frequency', $product->billing_frequency) === 'monthly' ? 'selected' : '' }}>Monthly</option>
                        <option value="quarterly" {{ old('billing_frequency', $product->billing_frequency) === 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                        <option value="annually" {{ old('billing_frequency', $product->billing_frequency) === 'annually' ? 'selected' : '' }}>Annually</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Required for recurring product types.</p>
                    @error('billing_frequency') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Hosting Integration (Stripe + WHM — shown for hosting only) --}}
                <div x-show="productType === 'hosting'" x-transition class="border border-blue-200 rounded-md p-4 bg-blue-50 space-y-4">
                    <h3 class="text-sm font-semibold text-blue-900">Hosting Integration</h3>

                    {{-- Stripe Price --}}
                    <div>
                        <label for="stripe_price_id" class="block text-sm font-medium text-gray-700">Stripe Subscription Price</label>
                        @if (!empty($stripePrices))
                            <select name="stripe_price_id" id="stripe_price_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select a Stripe price...</option>
                                @foreach ($stripePrices as $price)
                                    <option value="{{ $price['id'] }}" {{ old('stripe_price_id', $product->stripe_price_id) === $price['id'] ? 'selected' : '' }}>
                                        {{ $price['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" name="stripe_price_id" id="stripe_price_id"
                                   value="{{ old('stripe_price_id', $product->stripe_price_id) }}"
                                   placeholder="price_xxxxx"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        @endif
                        <p class="text-xs text-gray-400 mt-1">The Stripe recurring price used when a customer subscribes to this product.</p>
                        @error('stripe_price_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- WHM Package --}}
                    <div>
                        <label for="whm_package" class="block text-sm font-medium text-gray-700">WHM Package</label>
                        <select name="whm_package" id="whm_package"
                                class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">No auto-provisioning</option>
                            <option value="ckhostco_Basic" {{ old('whm_package', $product->whm_package) === 'ckhostco_Basic' ? 'selected' : '' }}>Basic</option>
                            <option value="ckhostco_Personal" {{ old('whm_package', $product->whm_package) === 'ckhostco_Personal' ? 'selected' : '' }}>Personal</option>
                            <option value="ckhostco_Personal Plus" {{ old('whm_package', $product->whm_package) === 'ckhostco_Personal Plus' ? 'selected' : '' }}>Personal Plus</option>
                            <option value="ckhostco_Business Plus" {{ old('whm_package', $product->whm_package) === 'ckhostco_Business Plus' ? 'selected' : '' }}>Business Plus</option>
                            <option value="ckhostco_Unlimited" {{ old('whm_package', $product->whm_package) === 'ckhostco_Unlimited' ? 'selected' : '' }}>Unlimited</option>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">If set, the system will auto-provision a cPanel account using this WHM package when the hosting is activated.</p>
                        @error('whm_package') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Stock Quantity (shown for equipment_rental and one_off) --}}
                <div x-show="productType === 'equipment_rental' || productType === 'one_off'" x-transition>
                    <label for="stock_quantity" class="block text-sm font-semibold text-gray-700">Stock Quantity</label>
                    <input type="number" name="stock_quantity" id="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity) }}" min="0"
                           class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Leave blank for unlimited">
                    <p class="text-xs text-gray-400 mt-1">Leave empty for unlimited stock.</p>
                    @error('stock_quantity') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Low Stock Threshold (shown for equipment_rental and one_off) --}}
                <div x-show="productType === 'equipment_rental' || productType === 'one_off'" x-transition>
                    <label for="low_stock_threshold" class="block text-sm font-semibold text-gray-700">Low Stock Threshold</label>
                    <input type="number" name="low_stock_threshold" id="low_stock_threshold" value="{{ old('low_stock_threshold', $product->low_stock_threshold) }}" min="1"
                           class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="e.g. 5">
                    <p class="text-xs text-gray-400 mt-1">Receive an alert when stock falls to or below this level.</p>
                    @error('low_stock_threshold') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Rental-specific fields (shown only for equipment_rental) --}}
                <div x-show="productType === 'equipment_rental'" x-transition class="border-t pt-5 space-y-5">
                    <h3 class="text-sm font-semibold text-gray-700">Rental Settings</h3>

                    <div class="grid grid-cols-2 gap-4">
                        {{-- Minimum Rental Days --}}
                        <div>
                            <label for="min_rental_days" class="block text-sm font-semibold text-gray-700">Minimum Rental Days</label>
                            <input type="number" name="min_rental_days" id="min_rental_days" value="{{ old('min_rental_days', $product->min_rental_days) }}" min="1"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="e.g. 1">
                            <p class="text-xs text-gray-400 mt-1">Minimum number of days a customer must rent.</p>
                            @error('min_rental_days') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Cooldown Days --}}
                        <div>
                            <label for="cooldown_days" class="block text-sm font-semibold text-gray-700">Cooldown Days</label>
                            <input type="number" name="cooldown_days" id="cooldown_days" value="{{ old('cooldown_days', $product->cooldown_days) }}" min="0"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="e.g. 0">
                            <p class="text-xs text-gray-400 mt-1">Days required between consecutive bookings.</p>
                            @error('cooldown_days') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Rental Agreement Text --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Rental Agreement Text</label>
                        <x-rich-text-editor name="rental_agreement_text" :value="old('rental_agreement_text', $product->rental_agreement_text)" placeholder="Enter the rental agreement terms customers must accept..." />
                        <p class="text-xs text-gray-400 mt-1">If provided, customers must accept these terms before completing a rental booking.</p>
                        @error('rental_agreement_text') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Delivery Instructions (shown for equipment_rental and one_off, hidden for hosting) --}}
                <div x-show="productType === 'equipment_rental' || productType === 'one_off'" x-transition>
                    <label for="delivery_instructions" class="block text-sm font-semibold text-gray-700">Delivery / Collection Instructions</label>
                    <textarea name="delivery_instructions" id="delivery_instructions" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Enter delivery or collection instructions for this product...">{{ old('delivery_instructions', $product->delivery_instructions) }}</textarea>
                    <p class="text-xs text-gray-400 mt-1">Plain-text instructions shown to customers and staff for physical item handling.</p>
                    @error('delivery_instructions') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Delivery Charge (shown for equipment_rental and one_off, hidden for hosting) --}}
                <div x-show="productType === 'equipment_rental' || productType === 'one_off'" x-transition>
                    <label for="delivery_charge" class="block text-sm font-semibold text-gray-700">Delivery Charge (&pound;)</label>
                    <input type="number" name="delivery_charge" id="delivery_charge" value="{{ old('delivery_charge', $product->delivery_charge) }}" step="0.01" min="0"
                           class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="0.00">
                    <p class="text-xs text-gray-400 mt-1">Leave empty or 0 for free delivery. Customers can also choose to collect from North Manchester for free.</p>
                    @error('delivery_charge') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Image Upload --}}
                <div>
                    <label for="image" class="block text-sm font-semibold text-gray-700">Product Image</label>
                    @if ($product->image_path)
                        <div class="mt-2 mb-2 flex items-center gap-3">
                            <img src="{{ Storage::url($product->image_path) }}" alt="{{ $product->name }}" class="w-20 h-20 rounded object-cover border">
                            <span class="text-xs text-gray-500">Current image</span>
                        </div>
                    @endif
                    <input type="file" name="image" id="image" accept="image/*"
                           class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-400 mt-1">Max 2MB. Leave empty to keep current image.</p>
                    @error('image') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Visibility Rules --}}
                <div class="border-t pt-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">Visibility Rules</h3>
                    <p class="text-xs text-gray-400 mb-3">Control which customers can see and purchase this product.</p>

                    <div class="space-y-3">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="radio" name="visibility_type" value="all" x-model="visibilityType"
                                   class="text-blue-600 focus:ring-blue-500">
                            All customers
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="radio" name="visibility_type" value="customers" x-model="visibilityType"
                                   class="text-blue-600 focus:ring-blue-500">
                            Specific customers
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="radio" name="visibility_type" value="tiers" x-model="visibilityType"
                                   class="text-blue-600 focus:ring-blue-500">
                            Specific tiers
                        </label>
                    </div>

                    {{-- Customer selection --}}
                    <div x-show="visibilityType === 'customers'" x-transition class="mt-3">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Select Customers</label>
                        <div class="max-h-48 overflow-y-auto border rounded-md p-2 space-y-1">
                            @foreach ($customers as $customer)
                                <label class="flex items-center gap-2 text-sm text-gray-700 hover:bg-gray-50 px-2 py-1 rounded cursor-pointer">
                                    <input type="checkbox" name="visibility_customers[]" value="{{ $customer->company_id }}"
                                           {{ in_array($customer->company_id, old('visibility_customers', $selectedCustomerIds ?? [])) ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    {{ $customer->company_name }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Tier selection --}}
                    <div x-show="visibilityType === 'tiers'" x-transition class="mt-3">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Select Tiers</label>
                        <div class="max-h-48 overflow-y-auto border rounded-md p-2 space-y-1">
                            @foreach ($tiers as $tier)
                                <label class="flex items-center gap-2 text-sm text-gray-700 hover:bg-gray-50 px-2 py-1 rounded cursor-pointer">
                                    <input type="checkbox" name="visibility_tiers[]" value="{{ $tier->id }}"
                                           {{ in_array($tier->id, old('visibility_tiers', $selectedTierIds ?? [])) ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    {{ $tier->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700">
                        Update Product
                    </button>
                    <a href="{{ route('admin.shop.products.index') }}" class="px-5 py-2.5 border rounded-md text-sm font-semibold hover:bg-gray-50">Cancel</a>
                </div>
            </div>
        </form>
    </div>

    {{-- Linked Assets Section (Equipment Rental only) --}}
    @if ($product->isEquipmentRental())
        <div class="bg-white rounded-lg border p-6 max-w-3xl mt-6" x-data="{ showLinkForm: false }">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Linked Assets</h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        <span class="font-medium text-green-700">{{ $availableAssetCount }} available</span> / {{ $totalAssetCount }} total
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button"
                            @click="showLinkForm = !showLinkForm"
                            class="px-3 py-2 text-sm font-medium text-blue-600 border border-blue-200 rounded-md hover:bg-blue-50">
                        Link Existing Asset
                    </button>
                    <a href="{{ route('admin.assets.create', ['product_id' => $product->id]) }}"
                       class="px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                        Create New Asset
                    </a>
                </div>
            </div>

            {{-- Link Existing Asset Form --}}
            <div x-show="showLinkForm" x-transition class="mb-4 p-4 bg-gray-50 rounded-md border">
                <form method="POST" action="{{ route('admin.shop.products.link-asset', $product) }}" class="flex items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <label for="asset_id" class="block text-sm font-medium text-gray-700 mb-1">Select Asset</label>
                        <select name="asset_id" id="asset_id" required
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">Choose an asset...</option>
                            @foreach (\App\Models\Asset::whereNull('product_id')->orderBy('device_name')->get() as $unlinkedAsset)
                                <option value="{{ $unlinkedAsset->device_id }}">
                                    {{ $unlinkedAsset->device_name }} — {{ $unlinkedAsset->serial_number ?: 'No serial' }}
                                    ({{ $unlinkedAsset->asset_status }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                        Link
                    </button>
                    <button type="button" @click="showLinkForm = false" class="px-4 py-2 text-sm font-medium text-gray-600 border rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                </form>
                @error('asset_id') <p class="text-red-600 text-sm mt-2">{{ $message }}</p> @enderror
            </div>

            {{-- Assets Table --}}
            @if ($linkedAssets->isEmpty())
                <div class="text-center py-8 text-gray-400 text-sm">
                    No assets linked to this product yet.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Device Name</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Serial Number</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Status</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Location</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($linkedAssets as $asset)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 font-medium text-gray-900">{{ $asset->device_name }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ $asset->serial_number ?: '—' }}</td>
                                    <td class="px-4 py-2">
                                        @php
                                            $statusColors = [
                                                'Available' => 'bg-green-100 text-green-700',
                                                'Rented Out' => 'bg-blue-100 text-blue-700',
                                                'Reserved' => 'bg-yellow-100 text-yellow-700',
                                                'In Repair' => 'bg-orange-100 text-orange-700',
                                                'Decommissioned' => 'bg-gray-100 text-gray-600',
                                            ];
                                            $colorClass = $statusColors[$asset->asset_status] ?? 'bg-gray-100 text-gray-600';
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $colorClass }}">
                                            {{ $asset->asset_status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-600">{{ $asset->location ?: '—' }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <form method="POST"
                                              action="{{ route('admin.shop.products.unlink-asset', [$product, $asset->device_id]) }}"
                                              onsubmit="return confirm('Unlink this asset from the product?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline text-xs font-medium">
                                                Unlink
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    <script>
        function productEditForm() {
            return {
                productType: '{{ old('product_type', $product->product_type) }}',
                visibilityType: '{{ old('visibility_type', $product->visibilityRule?->visibility_type ?? 'all') }}',
            }
        }
    </script>
</x-admin-layout>
