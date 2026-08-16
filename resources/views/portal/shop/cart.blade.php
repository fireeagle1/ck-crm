<x-portal-layout>
    <x-slot:title>Cart</x-slot:title>

    <h1 class="text-3xl font-bold tracking-tight mb-2">Your Cart</h1>
    <p class="text-gray-500 mb-6">
        {{ count($items) }} {{ Str::plural('item', count($items)) }} in your cart.
    </p>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif

    @if (session('error'))
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    @if (empty($items))
        {{-- Empty cart state --}}
        <div class="bg-white rounded-lg border p-8 text-center">
            <svg class="mx-auto w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
            </svg>
            <p class="text-gray-500 mb-4">Your cart is empty.</p>
            <a href="{{ route('portal.shop.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition">
                Browse Shop
            </a>
        </div>
    @else
        {{-- Cart items --}}
        <div class="bg-white rounded-lg border overflow-hidden mb-6">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Product</th>
                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Type</th>
                        <th class="px-5 py-3 text-center font-semibold text-gray-600">Qty</th>
                        <th class="px-5 py-3 text-right font-semibold text-gray-600">Price</th>
                        <th class="px-5 py-3 text-right font-semibold text-gray-600"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($items as $index => $item)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-gray-900">{{ $item['name'] }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    @switch($item['product_type'])
                                        @case('hosting') bg-blue-100 text-blue-700 @break
                                        @case('equipment_rental') bg-amber-100 text-amber-700 @break
                                        @case('one_off') bg-green-100 text-green-700 @break
                                    @endswitch
                                ">
                                    {{ str_replace('_', ' ', ucfirst($item['product_type'])) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-center">
                                @if ($item['product_type'] === 'one_off')
                                    {{-- Editable quantity for one-off items --}}
                                    <form method="POST" action="{{ route('portal.cart.updateQuantity', $index) }}" class="inline-flex items-center gap-1">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="quantity" value="{{ $item['quantity'] ?? 1 }}" id="qty-input-{{ $index }}">
                                        <button type="button"
                                                class="w-7 h-7 flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:bg-gray-200 transition text-sm font-bold disabled:opacity-50 disabled:cursor-not-allowed"
                                                aria-label="Decrease quantity"
                                                @if (($item['quantity'] ?? 1) <= 1) disabled @endif
                                                onclick="let i=document.getElementById('qty-input-{{ $index }}');i.value=Math.max(1,parseInt(i.value)-1);this.closest('form').submit()">
                                            &minus;
                                        </button>
                                        <input type="number" value="{{ $item['quantity'] ?? 1 }}" min="1"
                                               class="w-14 h-7 text-center text-sm border border-gray-300 rounded focus:border-blue-500 focus:ring-blue-500"
                                               aria-label="Quantity"
                                               onchange="document.getElementById('qty-input-{{ $index }}').value=this.value;this.closest('form').submit()">
                                        <button type="button"
                                                class="w-7 h-7 flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:bg-gray-200 transition text-sm font-bold"
                                                aria-label="Increase quantity"
                                                onclick="let i=document.getElementById('qty-input-{{ $index }}');i.value=parseInt(i.value)+1;this.closest('form').submit()">
                                            +
                                        </button>
                                    </form>
                                @elseif ($item['product_type'] === 'equipment_rental')
                                    {{-- Read-only quantity for rental items --}}
                                    <span class="text-gray-700">{{ $item['quantity'] ?? 1 }}</span>
                                @else
                                    <span class="text-gray-400">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                @if ($item['product_type'] === 'one_off' && ($item['quantity'] ?? 1) > 1)
                                    <p class="font-semibold text-gray-900">&pound;{{ number_format($item['total_price'] ?? $item['price'] * ($item['quantity'] ?? 1), 2) }}</p>
                                    <p class="text-xs text-gray-500">&pound;{{ number_format($item['price'], 2) }} &times; {{ $item['quantity'] }}</p>
                                @else
                                    <p class="font-semibold text-gray-900">&pound;{{ number_format($item['total_price'] ?? $item['price'], 2) }}</p>
                                    @if ($item['billing_frequency'])
                                        <p class="text-xs text-gray-500">/{{ $item['billing_frequency'] }}</p>
                                    @endif
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <form method="POST" action="{{ route('portal.cart.remove', $index) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium transition" title="Remove item">
                                        Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Total and checkout --}}
        <div class="bg-white rounded-lg border p-6">
            <div class="flex items-center justify-between mb-4">
                <span class="text-lg font-semibold text-gray-900">Total</span>
                <span class="text-2xl font-bold text-gray-900">&pound;{{ number_format($total, 2) }}</span>
            </div>

            <a href="{{ route('portal.cart.showCheckout') }}" class="block w-full px-5 py-3 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition text-center">
                Proceed to Checkout
            </a>

            <div class="mt-3 text-center">
                <a href="{{ route('portal.shop.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">
                    &larr; Continue Shopping
                </a>
            </div>
        </div>
    @endif
</x-portal-layout>
