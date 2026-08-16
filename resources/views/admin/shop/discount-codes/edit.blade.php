<x-admin-layout>
    <x-slot:title>Edit Discount Code</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Edit Discount Code</h1>
        <a href="{{ route('admin.shop.discount-codes.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Discount Codes</a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-lg border p-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.shop.discount-codes.update', $discountCode) }}">
            @csrf
            @method('PUT')

            <div class="space-y-5">
                {{-- Code --}}
                <div>
                    <label for="code" class="block text-sm font-semibold text-gray-700">Code <span class="text-red-500">*</span></label>
                    <input type="text" name="code" id="code" value="{{ old('code', $discountCode->code) }}" required
                           class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 uppercase"
                           placeholder="e.g. SUMMER20">
                    @error('code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Type and Value --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="type" class="block text-sm font-semibold text-gray-700">Discount Type <span class="text-red-500">*</span></label>
                        <select name="type" id="type" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="percentage" {{ old('type', $discountCode->type) === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                            <option value="fixed" {{ old('type', $discountCode->type) === 'fixed' ? 'selected' : '' }}>Fixed Amount (&pound;)</option>
                        </select>
                        @error('type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="value" class="block text-sm font-semibold text-gray-700">Value <span class="text-red-500">*</span></label>
                        <input type="number" name="value" id="value" value="{{ old('value', $discountCode->value) }}" required step="0.01" min="0.01"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('value') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Min Order & Max Discount --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="min_order_amount" class="block text-sm font-semibold text-gray-700">Minimum Order Amount (&pound;)</label>
                        <input type="number" name="min_order_amount" id="min_order_amount" value="{{ old('min_order_amount', $discountCode->min_order_amount) }}" step="0.01" min="0"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="No minimum">
                        @error('min_order_amount') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="max_discount_amount" class="block text-sm font-semibold text-gray-700">Max Discount (&pound;)</label>
                        <input type="number" name="max_discount_amount" id="max_discount_amount" value="{{ old('max_discount_amount', $discountCode->max_discount_amount) }}" step="0.01" min="0"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="No cap">
                        @error('max_discount_amount') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Usage Limit --}}
                <div>
                    <label for="usage_limit" class="block text-sm font-semibold text-gray-700">Usage Limit</label>
                    <input type="number" name="usage_limit" id="usage_limit" value="{{ old('usage_limit', $discountCode->usage_limit) }}" min="1"
                           class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Unlimited">
                    <p class="text-xs text-gray-400 mt-1">Used {{ $discountCode->times_used }} time(s) so far.</p>
                    @error('usage_limit') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Valid Period --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="valid_from" class="block text-sm font-semibold text-gray-700">Valid From</label>
                        <input type="datetime-local" name="valid_from" id="valid_from"
                               value="{{ old('valid_from', $discountCode->valid_from?->format('Y-m-d\TH:i')) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('valid_from') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="valid_until" class="block text-sm font-semibold text-gray-700">Valid Until</label>
                        <input type="datetime-local" name="valid_until" id="valid_until"
                               value="{{ old('valid_until', $discountCode->valid_until?->format('Y-m-d\TH:i')) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('valid_until') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Active Toggle --}}
                <div>
                    <label class="flex items-center gap-2">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $discountCode->is_active) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-700">Active</span>
                    </label>
                </div>

                {{-- Actions --}}
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700">
                        Update Discount Code
                    </button>
                    <a href="{{ route('admin.shop.discount-codes.index') }}" class="px-5 py-2.5 border rounded-md text-sm font-semibold hover:bg-gray-50">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
