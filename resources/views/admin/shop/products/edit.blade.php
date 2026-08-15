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
                    <label for="description" class="block text-sm font-semibold text-gray-700">Description <span class="text-red-500">*</span></label>
                    <textarea name="description" id="description" rows="4" required
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('description', $product->description) }}</textarea>
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
                        <label for="price" class="block text-sm font-semibold text-gray-700">Price (&pound;) <span class="text-red-500">*</span></label>
                        <input type="number" name="price" id="price" value="{{ old('price', $product->price) }}" required step="0.01" min="0.01"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('price') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Billing Frequency (shown for equipment_rental and hosting) --}}
                <div x-show="productType === 'equipment_rental' || productType === 'hosting'" x-transition>
                    <label for="billing_frequency" class="block text-sm font-semibold text-gray-700">Billing Frequency <span class="text-red-500">*</span></label>
                    <select name="billing_frequency" id="billing_frequency"
                            :required="productType === 'equipment_rental' || productType === 'hosting'"
                            class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select frequency...</option>
                        <option value="monthly" {{ old('billing_frequency', $product->billing_frequency) === 'monthly' ? 'selected' : '' }}>Monthly</option>
                        <option value="quarterly" {{ old('billing_frequency', $product->billing_frequency) === 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                        <option value="annually" {{ old('billing_frequency', $product->billing_frequency) === 'annually' ? 'selected' : '' }}>Annually</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Required for recurring product types.</p>
                    @error('billing_frequency') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
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

    <script>
        function productEditForm() {
            return {
                productType: '{{ old('product_type', $product->product_type) }}',
                visibilityType: '{{ old('visibility_type', $product->visibilityRule?->visibility_type ?? 'all') }}',
            }
        }
    </script>
</x-admin-layout>
