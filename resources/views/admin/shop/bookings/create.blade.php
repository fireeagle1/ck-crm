<x-admin-layout>
    <x-slot:title>Create Manual Booking</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Create Manual Booking</h1>
        <a href="{{ route('admin.shop.bookings.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200 border">
            &larr; Back to Bookings
        </a>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden max-w-2xl">
        <div class="px-4 py-3 border-b bg-gray-50">
            <h2 class="text-sm font-semibold text-gray-700">Booking Details</h2>
        </div>
        <form method="POST" action="{{ route('admin.shop.bookings.store') }}" class="p-6 space-y-5">
            @csrf

            {{-- Customer --}}
            <div>
                <label for="company_id" class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                <select name="company_id" id="company_id" required
                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('company_id') border-red-300 @enderror">
                    <option value="">Select a customer...</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->company_id }}" {{ old('company_id') == $customer->company_id ? 'selected' : '' }}>
                            {{ $customer->company_name }}
                        </option>
                    @endforeach
                </select>
                @error('company_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Product --}}
            <div>
                <label for="product_id" class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                <select name="product_id" id="product_id" required
                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('product_id') border-red-300 @enderror">
                    <option value="">Select a product...</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}"
                                data-min-days="{{ $product->min_rental_days }}"
                                data-price="{{ $product->price }}"
                                {{ old('product_id') == $product->id ? 'selected' : '' }}>
                            {{ $product->name }} (&pound;{{ number_format($product->price, 2) }}/day)
                        </option>
                    @endforeach
                </select>
                @error('product_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Dates --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" name="start_date" id="start_date" required
                           value="{{ old('start_date') }}"
                           min="{{ now()->format('Y-m-d') }}"
                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('start_date') border-red-300 @enderror">
                    @error('start_date')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" name="end_date" id="end_date" required
                           value="{{ old('end_date') }}"
                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('end_date') border-red-300 @enderror">
                    @error('end_date')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Quantity --}}
            <div>
                <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                <input type="number" name="quantity" id="quantity" required
                       value="{{ old('quantity', 1) }}" min="1"
                       class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('quantity') border-red-300 @enderror">
                @error('quantity')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Paid Offline --}}
            <div class="flex items-center gap-2">
                <input type="hidden" name="paid_offline" value="0">
                <input type="checkbox" name="paid_offline" id="paid_offline" value="1"
                       {{ old('paid_offline') ? 'checked' : '' }}
                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                <label for="paid_offline" class="text-sm text-gray-700">Paid offline</label>
            </div>
            <p class="text-xs text-gray-500 -mt-3">
                If checked, the order will be marked as "paid offline" and no Stripe payment will be processed.
                Otherwise, it will be marked as "pending" for later payment collection.
            </p>

            {{-- Submit --}}
            <div class="pt-4 border-t">
                <button type="submit"
                        class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition">
                    Create Booking
                </button>
            </div>
        </form>
    </div>
</x-admin-layout>
