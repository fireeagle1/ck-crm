<x-admin-layout>
    <x-slot:title>Shop Products</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Shop Products</h1>
        <a href="{{ route('admin.shop.products.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
            + Create Product
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.shop.products.index') }}" class="flex items-end gap-4 mb-6">
        <div>
            <label for="product_type" class="block text-xs font-medium text-gray-600 mb-1">Product Type</label>
            <select name="product_type" id="product_type" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All Types</option>
                <option value="equipment_rental" {{ request('product_type') === 'equipment_rental' ? 'selected' : '' }}>Equipment Rental</option>
                <option value="one_off" {{ request('product_type') === 'one_off' ? 'selected' : '' }}>One-Off Purchase</option>
                <option value="hosting" {{ request('product_type') === 'hosting' ? 'selected' : '' }}>Hosting</option>
            </select>
        </div>
        <div>
            <label for="archived" class="block text-xs font-medium text-gray-600 mb-1">Status</label>
            <select name="archived" id="archived" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All</option>
                <option value="0" {{ request('archived') === '0' ? 'selected' : '' }}>Active</option>
                <option value="1" {{ request('archived') === '1' ? 'selected' : '' }}>Archived</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200 border">
            Filter
        </button>
        @if(request()->hasAny(['product_type', 'archived']))
            <a href="{{ route('admin.shop.products.index') }}" class="text-sm text-blue-600 hover:underline">Clear</a>
        @endif
    </form>

    @if (session('success'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Product</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Type</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Price</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Stock</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($products as $product)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if ($product->image_path)
                                    <img src="{{ Storage::url($product->image_path) }}" alt="{{ $product->name }}" class="w-10 h-10 rounded object-cover">
                                @else
                                    <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif
                                <span class="font-medium text-gray-900">{{ $product->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $typeLabels = [
                                    'equipment_rental' => 'Equipment Rental',
                                    'one_off' => 'One-Off Purchase',
                                    'hosting' => 'Hosting',
                                ];
                                $typeColors = [
                                    'equipment_rental' => 'bg-purple-100 text-purple-700',
                                    'one_off' => 'bg-amber-100 text-amber-700',
                                    'hosting' => 'bg-blue-100 text-blue-700',
                                ];
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $typeColors[$product->product_type] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $typeLabels[$product->product_type] ?? $product->product_type }}
                            </span>
                        </td>
                        <td class="px-4 py-3">&pound;{{ number_format($product->price, 2) }}
                            @if ($product->billing_frequency)
                                <span class="text-xs text-gray-500">/ {{ $product->billing_frequency }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($product->stock_quantity === null)
                                <span class="text-gray-400">Unlimited</span>
                            @elseif ($product->stock_quantity === 0)
                                <span class="text-red-600 font-medium">Out of stock</span>
                            @else
                                {{ $product->stock_quantity }}
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($product->is_archived)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700">Archived</span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Active</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.shop.products.edit', $product) }}" class="text-blue-600 hover:underline text-sm font-medium">Edit</a>
                                @if ($product->is_archived)
                                    <form method="POST" action="{{ route('admin.shop.products.restore', $product) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-green-600 hover:underline text-sm font-medium">Restore</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.shop.products.archive', $product) }}" class="inline"
                                          onsubmit="return confirm('Archive this product? It will be hidden from the shop.')">
                                        @csrf
                                        <button type="submit" class="text-red-600 hover:underline text-sm font-medium">Archive</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No products found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->withQueryString()->links() }}</div>
</x-admin-layout>
