<x-admin-layout>
    <x-slot:title>Create Product</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Create Product</h1>
        <a href="{{ route('admin.shop.products.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Products</a>
    </div>

    <div class="bg-white rounded-lg border p-6 max-w-3xl" x-data="productForm()">
        <form method="POST" action="{{ route('admin.shop.products.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="space-y-5">
                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-semibold text-gray-700">Product Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="e.g. Basic Hosting Package">
                    @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Description --}}
                <div>
                    <label for="description" class="block text-sm font-semibold text-gray-700">Description <span class="text-red-500">*</span></label>
                    <textarea name="description" id="description" rows="4" required
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Describe the product...">{{ old('description') }}</textarea>
                    @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Product Type & Price --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="product_type" class="block text-sm font-semibold text-gray-700">Product Type <span class="text-red-500">*</span></label>
                        <select name="product_type" id="product_type" required x-model="productType"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select type...</option>
                            <option value="equipment_rental" {{ old('product_type') === 'equipment_rental' ? 'selected' : '' }}>Equipment Rental</option>
                            <option value="one_off" {{ old('product_type') === 'one_off' ? 'selected' : '' }}>One-Off Purchase</option>
                            <option value="hosting" {{ old('product_type') === 'hosting' ? 'selected' : '' }}>Hosting</option>
                        </select>
                        @error('product_type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="price" class="block text-sm font-semibold text-gray-700">Price (&pound;) <span class="text-red-500">*</span></label>
                        <input type="number" name="price" id="price" value="{{ old('price') }}" required step="0.01" min="0.01"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="0.00">
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
                        <option value="monthly" {{ old('billing_frequency') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                        <option value="quarterly" {{ old('billing_frequency') === 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                        <option value="annually" {{ old('billing_frequency') === 'annually' ? 'selected' : '' }}>Annually</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Required for recurring product types.</p>
                    @error('billing_frequency') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Stock Quantity (shown for equipment_rental and one_off) --}}
                <div x-show="productType === 'equipment_rental' || productType === 'one_off'" x-transition>
                    <label for="stock_quantity" class="block text-sm font-semibold text-gray-700">Stock Quantity</label>
                    <input type="number" name="stock_quantity" id="stock_quantity" value="{{ old('stock_quantity') }}" min="0"
                           class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Leave blank for unlimited">
                    <p class="text-xs text-gray-400 mt-1">Leave empty for unlimited stock.</p>
                    @error('stock_quantity') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Low Stock Threshold (shown for equipment_rental and one_off) --}}
                <div x-show="productType === 'equipment_rental' || productType === 'one_off'" x-transition>
                    <label for="low_stock_threshold" class="block text-sm font-semibold text-gray-700">Low Stock Threshold</label>
                    <input type="number" name="low_stock_threshold" id="low_stock_threshold" value="{{ old('low_stock_threshold') }}" min="1"
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
                            <input type="number" name="min_rental_days" id="min_rental_days" value="{{ old('min_rental_days') }}" min="1"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="e.g. 1">
                            <p class="text-xs text-gray-400 mt-1">Minimum number of days a customer must rent.</p>
                            @error('min_rental_days') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Cooldown Days --}}
                        <div>
                            <label for="cooldown_days" class="block text-sm font-semibold text-gray-700">Cooldown Days</label>
                            <input type="number" name="cooldown_days" id="cooldown_days" value="{{ old('cooldown_days') }}" min="0"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="e.g. 0">
                            <p class="text-xs text-gray-400 mt-1">Days required between consecutive bookings.</p>
                            @error('cooldown_days') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Rental Agreement Text --}}
                    <div>
                        <label for="rental_agreement_text" class="block text-sm font-semibold text-gray-700">Rental Agreement Text</label>
                        <textarea name="rental_agreement_text" id="rental_agreement_text" rows="6"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Enter the rental agreement terms customers must accept...">{{ old('rental_agreement_text') }}</textarea>
                        <p class="text-xs text-gray-400 mt-1">If provided, customers must accept these terms before completing a rental booking.</p>
                        @error('rental_agreement_text') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Delivery Instructions (shown for equipment_rental and one_off, hidden for hosting) --}}
                <div x-show="productType === 'equipment_rental' || productType === 'one_off'" x-transition>
                    <label for="delivery_instructions" class="block text-sm font-semibold text-gray-700">Delivery / Collection Instructions</label>
                    <textarea name="delivery_instructions" id="delivery_instructions" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Enter delivery or collection instructions for this product...">{{ old('delivery_instructions') }}</textarea>
                    <p class="text-xs text-gray-400 mt-1">Plain-text instructions shown to customers and staff for physical item handling.</p>
                    @error('delivery_instructions') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Image Upload --}}
                <div>
                    <label for="image" class="block text-sm font-semibold text-gray-700">Product Image</label>
                    <input type="file" name="image" id="image" accept="image/*"
                           class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-400 mt-1">Max 2MB. JPG, PNG, or GIF.</p>
                    @error('image') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Visibility --}}
                <div class="border-t pt-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Visibility</h3>
                    <div class="space-y-3">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="radio" name="visibility_type" value="all" x-model="visibilityType"
                                   class="text-blue-600 focus:ring-blue-500" {{ old('visibility_type', 'all') === 'all' ? 'checked' : '' }}>
                            All customers
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="radio" name="visibility_type" value="customers" x-model="visibilityType"
                                   class="text-blue-600 focus:ring-blue-500" {{ old('visibility_type') === 'customers' ? 'checked' : '' }}>
                            Specific customers
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="radio" name="visibility_type" value="tiers" x-model="visibilityType"
                                   class="text-blue-600 focus:ring-blue-500" {{ old('visibility_type') === 'tiers' ? 'checked' : '' }}>
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
                                           {{ in_array($customer->company_id, old('visibility_customers', [])) ? 'checked' : '' }}
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
                                           {{ in_array($tier->id, old('visibility_tiers', [])) ? 'checked' : '' }}
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
                        Create Product
                    </button>
                    <a href="{{ route('admin.shop.products.index') }}" class="px-5 py-2.5 border rounded-md text-sm font-semibold hover:bg-gray-50">Cancel</a>
                </div>
            </div>
        </form>
    </div>

    <script>
        function productForm() {
            return {
                productType: '{{ old('product_type', '') }}',
                visibilityType: '{{ old('visibility_type', 'all') }}',
            }
        }
    </script>
</x-admin-layout>
