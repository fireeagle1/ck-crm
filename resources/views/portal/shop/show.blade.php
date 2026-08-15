<x-portal-layout>
    <x-slot:title>{{ $product->name }}</x-slot:title>

    {{-- Back to shop link --}}
    <a href="{{ route('portal.shop.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-6 transition">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to Shop
    </a>

    <div class="bg-white rounded-lg border overflow-hidden">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-0">
            {{-- Product image --}}
            <div class="h-64 md:h-full bg-gray-100 min-h-[300px]">
                @if ($product->image_path)
                    <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex items-center justify-center">
                        <svg class="w-24 h-24 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                @endif
            </div>

            {{-- Product details --}}
            <div class="p-6 md:p-8 flex flex-col">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <h1 class="text-2xl font-bold text-gray-900">{{ $product->name }}</h1>
                    <span class="shrink-0 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                        @switch($product->product_type)
                            @case('hosting') bg-blue-100 text-blue-700 @break
                            @case('equipment_rental') bg-amber-100 text-amber-700 @break
                            @case('one_off') bg-green-100 text-green-700 @break
                        @endswitch
                    ">
                        {{ str_replace('_', ' ', ucfirst($product->product_type)) }}
                    </span>
                </div>

                {{-- Price --}}
                <div class="mb-4">
                    <p class="text-3xl font-bold text-gray-900">
                        &pound;{{ number_format($product->price, 2) }}
                    </p>
                    @if ($product->billing_frequency)
                        <p class="text-sm text-gray-500 mt-1">
                            Billed {{ $product->billing_frequency }}
                        </p>
                    @endif
                </div>

                {{-- Description --}}
                <div class="text-sm text-gray-600 leading-relaxed mb-6 flex-1">
                    {!! nl2br(e($product->description)) !!}
                </div>

                {{-- Availability and Add to Cart --}}
                @if ($product->isAvailable())
                    <form method="POST" action="{{ route('portal.cart.add', $product) }}">
                        @csrf
                        <button type="submit" class="w-full px-5 py-3 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition">
                            Add to Cart
                        </button>
                    </form>
                @else
                    <div class="rounded-lg bg-red-50 border border-red-200 p-4 text-center">
                        <p class="text-sm font-semibold text-red-700">Out of Stock</p>
                        <p class="text-xs text-red-600 mt-1">This product is currently unavailable for purchase.</p>
                    </div>
                    <button disabled class="w-full mt-3 px-5 py-3 bg-gray-300 text-gray-500 rounded-md text-sm font-semibold cursor-not-allowed">
                        Add to Cart
                    </button>
                @endif

                {{-- Added to cart prompt --}}
                @if (session('added_to_cart'))
                    <div class="mt-4 rounded-lg bg-green-50 border border-green-200 p-5 text-center" x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'center' })">
                        <svg class="mx-auto w-8 h-8 text-green-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm font-semibold text-green-800 mb-1">'{{ session('added_to_cart') }}' added to your cart</p>
                        <p class="text-xs text-green-700 mb-4">What would you like to do next?</p>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <a href="{{ route('portal.cart.index') }}" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition text-center">
                                Checkout Now
                            </a>
                            <a href="{{ route('portal.shop.index') }}" class="flex-1 px-4 py-2.5 bg-white text-gray-700 border border-gray-300 rounded-md text-sm font-semibold hover:bg-gray-50 transition text-center">
                                Continue Shopping
                            </a>
                        </div>
                    </div>
                @endif

                {{-- Success message --}}
                @if (session('success'))
                    <div class="mt-4 rounded-lg bg-green-50 border border-green-200 p-3 text-center">
                        <p class="text-sm text-green-700">{{ session('success') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-portal-layout>
