<x-portal-layout>
    <x-slot:title>Shop</x-slot:title>

    <h1 class="text-3xl font-bold tracking-tight mb-2">Shop</h1>
    <p class="text-gray-500 mb-6">Browse available products and services.</p>

    {{-- Search and filter bar --}}
    <div class="bg-white rounded-lg border p-4 mb-6">
        <form method="GET" action="{{ route('portal.shop.index') }}" class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search products..."
                       class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
            </div>
            <div class="sm:w-48">
                <select name="type" class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <option value="">All Types</option>
                    <option value="equipment_rental" {{ request('type') === 'equipment_rental' ? 'selected' : '' }}>Equipment Rental</option>
                    <option value="one_off" {{ request('type') === 'one_off' ? 'selected' : '' }}>One-Off Purchase</option>
                    <option value="hosting" {{ request('type') === 'hosting' ? 'selected' : '' }}>Hosting</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition">
                Search
            </button>
            @if (request('search') || request('type'))
                <a href="{{ route('portal.shop.index') }}" class="px-4 py-2 border rounded-md text-sm font-semibold hover:bg-gray-50 transition text-center">
                    Clear
                </a>
            @endif
        </form>
    </div>

    {{-- Active filters indicator --}}
    @if (request('search') || request('type'))
        <div class="mb-4 flex flex-wrap gap-2 items-center">
            <span class="text-sm text-gray-500">Showing results for:</span>
            @if (request('search'))
                <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                    Search: "{{ request('search') }}"
                </span>
            @endif
            @if (request('type'))
                <span class="inline-flex items-center rounded-full bg-purple-50 px-3 py-1 text-xs font-medium text-purple-700">
                    Type: {{ str_replace('_', ' ', ucfirst(request('type'))) }}
                </span>
            @endif
        </div>
    @endif

    {{-- Product grid --}}
    @if ($products->isEmpty())
        <div class="bg-white rounded-lg border p-8 text-center">
            <p class="text-gray-500">No products found.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($products as $product)
                <div class="bg-white rounded-lg border overflow-hidden flex flex-col hover:shadow-md transition">
                    {{-- Product image --}}
                    <div class="aspect-[4/3] bg-gray-100 relative overflow-hidden">
                        @if ($product->image_path)
                            <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}" class="w-full h-full object-contain p-2">
                        @else
                            <div class="w-full h-full flex items-center justify-center">
                                <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                            </div>
                        @endif
                        @if (!$product->isAvailable())
                            <span class="absolute top-2 right-2 inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">
                                Unavailable
                            </span>
                        @endif
                    </div>

                    {{-- Product info --}}
                    <div class="p-4 flex-1 flex flex-col">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <h3 class="font-semibold text-gray-900">{{ $product->name }}</h3>
                            <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                @switch($product->product_type)
                                    @case('hosting') bg-blue-100 text-blue-700 @break
                                    @case('equipment_rental') bg-amber-100 text-amber-700 @break
                                    @case('one_off') bg-green-100 text-green-700 @break
                                @endswitch
                            ">
                                {{ str_replace('_', ' ', ucfirst($product->product_type)) }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 mb-3 line-clamp-2 flex-1">{{ Str::limit($product->description, 100) }}</p>
                        <div class="flex items-center justify-between mt-auto">
                            <p class="font-bold text-gray-900">
                                &pound;{{ number_format($product->price, 2) }}
                                @if ($product->isEquipmentRental())
                                    <span class="text-xs font-normal text-gray-500">/day</span>
                                @elseif ($product->billing_frequency)
                                    <span class="text-xs font-normal text-gray-500">/{{ $product->billing_frequency }}</span>
                                @endif
                            </p>
                            <a href="{{ route('portal.shop.show', $product) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800 transition">
                                View Details &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="mt-6">{{ $products->withQueryString()->links() }}</div>
    @endif
</x-portal-layout>
