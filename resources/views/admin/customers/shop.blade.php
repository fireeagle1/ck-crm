<x-admin-layout>
    <x-slot:title>{{ $customer->company_name }} — Shop & Rentals</x-slot:title>

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">{{ $customer->company_name }}</h1>
            <p class="text-sm text-gray-500 mt-1">Shop & Rental History</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex items-center px-4 py-2 border rounded-md text-sm font-semibold hover:bg-gray-50">
                &larr; Customer Profile
            </a>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
        <div class="bg-white rounded-lg p-4 border">
            <p class="text-xs font-medium text-gray-500 uppercase">Rental Spend</p>
            <p class="text-2xl font-bold mt-1">&pound;{{ number_format($kpis['total_rental_spend'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 border">
            <p class="text-xs font-medium text-gray-500 uppercase">Purchase Spend</p>
            <p class="text-2xl font-bold mt-1">&pound;{{ number_format($kpis['total_purchase_spend'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 border">
            <p class="text-xs font-medium text-gray-500 uppercase">Orders</p>
            <p class="text-2xl font-bold mt-1">{{ $kpis['order_count'] }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 border">
            <p class="text-xs font-medium text-gray-500 uppercase">Rentals</p>
            <p class="text-2xl font-bold mt-1">{{ $kpis['rental_count'] }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 border">
            <p class="text-xs font-medium text-gray-500 uppercase">Avg Order Value</p>
            <p class="text-2xl font-bold mt-1">&pound;{{ number_format($kpis['avg_order_value'], 2) }}</p>
        </div>
    </div>

    {{-- Loyalty Summary --}}
    <div class="bg-white rounded-lg border p-5 mb-6">
        <h2 class="font-bold mb-3">Loyalty Summary</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm">
            <div>
                <dt class="text-gray-500">Customer Since</dt>
                <dd class="font-medium">{{ $kpis['customer_since']->format('M j, Y') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Lifetime Spend</dt>
                <dd class="font-medium">&pound;{{ number_format($kpis['lifetime_spend'], 2) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Tier</dt>
                <dd class="font-medium">
                    @if ($customer->tiers->isNotEmpty())
                        @foreach ($customer->tiers as $tier)
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-purple-100 text-purple-700">
                                {{ $tier->name }}
                            </span>
                        @endforeach
                    @else
                        <span class="text-gray-400">No tier assigned</span>
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    {{-- Filter Bar --}}
    <form method="GET" action="{{ route('admin.customers.shop', $customer) }}" class="flex flex-wrap items-end gap-4 mb-6">
        <div>
            <label for="date_from" class="block text-xs font-medium text-gray-600 mb-1">From</label>
            <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}"
                   class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
            <label for="date_to" class="block text-xs font-medium text-gray-600 mb-1">To</label>
            <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}"
                   class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
            <label for="product_type" class="block text-xs font-medium text-gray-600 mb-1">Product Type</label>
            <select name="product_type" id="product_type" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All</option>
                <option value="one_off" {{ $productType === 'one_off' ? 'selected' : '' }}>One-Off Purchase</option>
                <option value="equipment_rental" {{ $productType === 'equipment_rental' ? 'selected' : '' }}>Equipment Rental</option>
                <option value="hosting" {{ $productType === 'hosting' ? 'selected' : '' }}>Hosting</option>
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200 border">
                Filter
            </button>
            @if ($dateFrom || $dateTo || $productType)
                <a href="{{ route('admin.customers.shop', $customer) }}" class="text-sm text-blue-600 hover:underline">Clear</a>
            @endif
        </div>
    </form>

    {{-- Orders Table --}}
    <div class="bg-white rounded-lg border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b">
            <h2 class="font-bold">Orders ({{ $orders->total() }})</h2>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">ID</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Date</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Items</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Type</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Total</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Payment</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Fulfilment</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($orders as $order)
                    @php
                        $paymentColors = [
                            'paid' => 'bg-green-100 text-green-700',
                            'paid_offline' => 'bg-green-100 text-green-700',
                            'failed' => 'bg-red-100 text-red-700',
                            'pending' => 'bg-yellow-100 text-yellow-700',
                            'refunded' => 'bg-gray-100 text-gray-700',
                        ];
                        $fulfilmentColors = [
                            'completed' => 'bg-green-100 text-green-700',
                            'awaiting_fulfilment' => 'bg-blue-100 text-blue-700',
                            'pending' => 'bg-gray-100 text-gray-700',
                        ];
                        $typeColors = [
                            'equipment_rental' => 'bg-purple-100 text-purple-700',
                            'one_off' => 'bg-amber-100 text-amber-700',
                            'hosting' => 'bg-blue-100 text-blue-700',
                        ];
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium">#{{ $order->id }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $order->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-2">
                            @foreach ($order->items->take(3) as $item)
                                <span class="text-gray-700">{{ $item->product->name ?? $item->product_name ?? 'Unknown' }}</span>@if (!$loop->last), @endif
                            @endforeach
                            @if ($order->items->count() > 3)
                                <span class="text-gray-400">+{{ $order->items->count() - 3 }} more</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @php $types = $order->items->pluck('product_type')->unique(); @endphp
                            @foreach ($types as $type)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $typeColors[$type] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ ucwords(str_replace('_', ' ', $type)) }}
                                </span>
                            @endforeach
                        </td>
                        <td class="px-4 py-2 font-medium">&pound;{{ number_format($order->total_amount, 2) }}</td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $paymentColors[$order->payment_status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ ucwords(str_replace('_', ' ', $order->payment_status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $fulfilmentColors[$order->fulfilment_status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ ucwords(str_replace('_', ' ', $order->fulfilment_status ?? 'pending')) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('admin.shop.orders.show', $order) }}" class="text-blue-600 hover:underline text-xs">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-4 text-center text-gray-500">No orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($orders->hasPages())
            <div class="px-4 py-3 border-t">
                {{ $orders->withQueryString()->links() }}
            </div>
        @endif
    </div>

    {{-- Active Bookings --}}
    <div class="bg-white rounded-lg border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b">
            <h2 class="font-bold">Active Bookings ({{ $activeBookings->count() }})</h2>
        </div>
        @php
            $stageColors = [
                'ordered' => 'bg-gray-100 text-gray-700',
                'packing' => 'bg-yellow-100 text-yellow-700',
                'ready' => 'bg-blue-100 text-blue-700',
                'checked_out' => 'bg-indigo-100 text-indigo-700',
                'returned' => 'bg-teal-100 text-teal-700',
                'inspected' => 'bg-green-100 text-green-700',
            ];
        @endphp
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Product</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Dates</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Stage</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Assets</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Days Remaining</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($activeBookings as $booking)
                    @php
                        $daysRemaining = now()->diffInDays($booking->end_date, false);
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium">{{ $booking->product->name ?? 'Unknown' }}</td>
                        <td class="px-4 py-2 text-gray-500">
                            {{ $booking->start_date->format('d M') }} &ndash; {{ $booking->end_date->format('d M Y') }}
                        </td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $stageColors[$booking->fulfilment_stage] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ ucwords(str_replace('_', ' ', $booking->fulfilment_stage)) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-gray-600">
                            {{ $booking->assignedAssets->count() }} assigned
                        </td>
                        <td class="px-4 py-2">
                            @if ($daysRemaining > 0)
                                <span class="text-gray-700">{{ (int) $daysRemaining }} {{ Str::plural('day', (int) $daysRemaining) }}</span>
                            @elseif ($daysRemaining == 0)
                                <span class="text-amber-600 font-medium">Due today</span>
                            @else
                                <span class="text-red-600 font-medium">{{ abs((int) $daysRemaining) }} {{ Str::plural('day', abs((int) $daysRemaining)) }} overdue</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('admin.shop.bookings.show', $booking) }}" class="text-blue-600 hover:underline text-xs">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-4 text-center text-gray-500">No active bookings.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Past Bookings --}}
    <div class="bg-white rounded-lg border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b">
            <h2 class="font-bold">Past Bookings ({{ $pastBookings->total() }})</h2>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Product</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Dates</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Returned</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Inspection</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Total</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($pastBookings as $booking)
                    @php
                        $returnInspection = $booking->inspections->where('type', 'return')->first();
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium">{{ $booking->product->name ?? 'Unknown' }}</td>
                        <td class="px-4 py-2 text-gray-500">
                            {{ $booking->start_date->format('d M') }} &ndash; {{ $booking->end_date->format('d M Y') }}
                        </td>
                        <td class="px-4 py-2 text-gray-500">
                            {{ $booking->returned_at ? $booking->returned_at->format('d M Y') : ($booking->end_date->format('d M Y')) }}
                        </td>
                        <td class="px-4 py-2">
                            @if ($returnInspection)
                                @if ($returnInspection->damage_flagged)
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700">Damage</span>
                                @else
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Good</span>
                                @endif
                                @if ($returnInspection->condition_notes)
                                    <p class="text-xs text-gray-500 mt-1 truncate max-w-[200px]">{{ Str::limit($returnInspection->condition_notes, 50) }}</p>
                                @endif
                            @else
                                <span class="text-xs text-gray-400">No inspection</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 font-medium">&pound;{{ number_format($booking->total_price ?? 0, 2) }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('admin.shop.bookings.show', $booking) }}" class="text-blue-600 hover:underline text-xs">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-4 text-center text-gray-500">No past bookings.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($pastBookings->hasPages())
            <div class="px-4 py-3 border-t">
                {{ $pastBookings->withQueryString()->links() }}
            </div>
        @endif
    </div>

    {{-- Documents --}}
    <div class="bg-white rounded-lg border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b">
            <h2 class="font-bold">Documents</h2>
        </div>
        <div class="divide-y">
            @forelse ($documentsByOrder as $orderId => $docs)
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">
                        @if ($orderId)
                            Order #{{ $orderId }}
                        @else
                            Unlinked Documents
                        @endif
                        <span class="text-gray-400 font-normal ml-2">{{ $docs->first()['date']->format('d M Y') }}</span>
                    </h3>
                    <div class="space-y-2">
                        @foreach ($docs as $doc)
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-2">
                                    @if ($doc['type'] === 'Invoice PDF')
                                        <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-gray-700">Invoice PDF</span>
                                    @else
                                        <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-gray-700">Rental Agreement</span>
                                        @if (isset($doc['product_name']))
                                            <span class="text-gray-400">&mdash; {{ $doc['product_name'] }}</span>
                                        @endif
                                    @endif
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-xs text-gray-400">{{ $doc['date']->format('d M Y') }}</span>
                                    @if ($doc['type'] === 'Invoice PDF' && isset($doc['download_route']))
                                        <a href="{{ $doc['download_route'] }}" class="text-blue-600 hover:underline text-xs font-medium">Download</a>
                                    @elseif ($doc['type'] === 'Rental Agreement' && isset($doc['booking_id']))
                                        <a href="{{ route('admin.shop.bookings.show', $doc['booking_id']) }}" class="text-blue-600 hover:underline text-xs font-medium">View</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="px-5 py-4 text-center text-gray-500 text-sm">No documents available.</div>
            @endforelse
        </div>
    </div>
</x-admin-layout>
