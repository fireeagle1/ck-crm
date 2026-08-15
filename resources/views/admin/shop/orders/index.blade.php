<x-admin-layout>
    <x-slot:title>Shop Orders</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Shop Orders</h1>
    </div>

    {{-- Revenue Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        @php
            $typeLabels = [
                'equipment_rental' => 'Equipment Rental',
                'one_off' => 'One-Off Purchase',
                'hosting' => 'Hosting',
            ];
            $typeColors = [
                'equipment_rental' => 'border-purple-200 bg-purple-50',
                'one_off' => 'border-amber-200 bg-amber-50',
                'hosting' => 'border-blue-200 bg-blue-50',
            ];
        @endphp
        @foreach (['equipment_rental', 'one_off', 'hosting'] as $type)
            @php
                $summary = $revenueSummary[$type] ?? null;
            @endphp
            <div class="rounded-lg border p-4 {{ $typeColors[$type] ?? 'border-gray-200 bg-white' }}">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ $typeLabels[$type] }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">
                    &pound;{{ number_format($summary->total_revenue ?? 0, 2) }}
                </p>
                <p class="text-sm text-gray-500 mt-1">{{ $summary->item_count ?? 0 }} {{ Str::plural('item', $summary->item_count ?? 0) }}</p>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.shop.orders.index') }}" class="flex flex-wrap items-end gap-4 mb-6">
        <div>
            <label for="fulfilment_status" class="block text-xs font-medium text-gray-600 mb-1">Fulfilment Status</label>
            <select name="fulfilment_status" id="fulfilment_status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All</option>
                <option value="pending" {{ request('fulfilment_status') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="awaiting_fulfilment" {{ request('fulfilment_status') === 'awaiting_fulfilment' ? 'selected' : '' }}>Awaiting Fulfilment</option>
                <option value="completed" {{ request('fulfilment_status') === 'completed' ? 'selected' : '' }}>Completed</option>
            </select>
        </div>
        <div>
            <label for="product_type" class="block text-xs font-medium text-gray-600 mb-1">Product Type</label>
            <select name="product_type" id="product_type" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All</option>
                <option value="one_off" {{ request('product_type') === 'one_off' ? 'selected' : '' }}>One-Off Purchase</option>
                <option value="equipment_rental" {{ request('product_type') === 'equipment_rental' ? 'selected' : '' }}>Equipment Rental</option>
                <option value="hosting" {{ request('product_type') === 'hosting' ? 'selected' : '' }}>Hosting</option>
            </select>
        </div>
        <div>
            <label for="customer" class="block text-xs font-medium text-gray-600 mb-1">Customer</label>
            <select name="customer" id="customer" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->company_id }}" {{ request('customer') == $customer->company_id ? 'selected' : '' }}>
                        {{ $customer->company_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="date_from" class="block text-xs font-medium text-gray-600 mb-1">From</label>
            <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}"
                   class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
            <label for="date_to" class="block text-xs font-medium text-gray-600 mb-1">To</label>
            <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}"
                   class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200 border">
                Filter
            </button>
            @if(request()->hasAny(['fulfilment_status', 'product_type', 'customer', 'date_from', 'date_to']))
                <a href="{{ route('admin.shop.orders.index') }}" class="text-sm text-blue-600 hover:underline">Clear</a>
            @endif
        </div>
    </form>

    @if (session('success'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    {{-- Orders Table --}}
    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">ID</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Customer</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Total</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Payment</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Fulfilment</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Date</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($orders as $order)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">#{{ $order->id }}</td>
                        <td class="px-4 py-3">{{ $order->customer?->company_name ?? 'N/A' }}</td>
                        <td class="px-4 py-3">&pound;{{ number_format($order->total_amount, 2) }}</td>
                        <td class="px-4 py-3">
                            @php
                                $paymentColors = [
                                    'paid' => 'bg-green-100 text-green-700',
                                    'failed' => 'bg-red-100 text-red-700',
                                    'pending' => 'bg-yellow-100 text-yellow-700',
                                ];
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $paymentColors[$order->payment_status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ ucfirst($order->payment_status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $fulfilmentColors = [
                                    'completed' => 'bg-green-100 text-green-700',
                                    'awaiting_fulfilment' => 'bg-blue-100 text-blue-700',
                                    'pending' => 'bg-gray-100 text-gray-700',
                                ];
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $fulfilmentColors[$order->fulfilment_status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ ucwords(str_replace('_', ' ', $order->fulfilment_status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $order->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.shop.orders.show', $order) }}" class="text-blue-600 hover:underline text-sm font-medium">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">No orders found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $orders->withQueryString()->links() }}</div>
</x-admin-layout>
