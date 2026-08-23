<x-admin-layout>
    <x-slot:title>Bookings</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Bookings</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.shop.bookings.calendar') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200 border">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Calendar View
            </a>
            <a href="{{ route('admin.shop.bookings.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition">
                + Manual Booking
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.shop.bookings.index') }}" class="flex flex-wrap items-end gap-4 mb-6">
        <div>
            <label for="status" class="block text-xs font-medium text-gray-600 mb-1">Status</label>
            <select name="status" id="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All</option>
                <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="returned" {{ request('status') === 'returned' ? 'selected' : '' }}>Returned</option>
                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
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
            <label for="product" class="block text-xs font-medium text-gray-600 mb-1">Product</label>
            <select name="product" id="product" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" {{ request('product') == $product->id ? 'selected' : '' }}>
                        {{ $product->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200 border">
                Filter
            </button>
            @if(request()->hasAny(['status', 'customer', 'product']))
                <a href="{{ route('admin.shop.bookings.index') }}" class="text-sm text-blue-600 hover:underline">Clear</a>
            @endif
        </div>
    </form>

    @if (session('success'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    {{-- Bookings Table --}}
    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">ID</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Customer</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Product</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Start Date</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">End Date</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Qty</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($bookings as $booking)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">#{{ $booking->id }}</td>
                        <td class="px-4 py-3">{{ $booking->customer?->company_name ?? 'N/A' }}</td>
                        <td class="px-4 py-3">{{ $booking->product?->name ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $booking->start_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $booking->end_date->format('d M Y') }}</td>
                        <td class="px-4 py-3">{{ $booking->quantity }}</td>
                        <td class="px-4 py-3">
                            @php
                                $statusColors = [
                                    'confirmed' => 'bg-blue-100 text-blue-700',
                                    'active' => 'bg-green-100 text-green-700',
                                    'returned' => 'bg-gray-100 text-gray-700',
                                    'cancelled' => 'bg-red-100 text-red-700',
                                ];
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColors[$booking->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ ucfirst($booking->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2 flex-wrap">
                                <a href="{{ route('admin.shop.bookings.show', $booking) }}" class="text-blue-600 hover:underline text-sm font-medium">View</a>

                                {{-- Resend Confirmation (always visible) --}}
                                <form method="POST" action="{{ route('admin.bookings.resend-confirmation', $booking) }}">
                                    @csrf
                                    <button type="submit" class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200">
                                        Resend Confirmation
                                    </button>
                                </form>

                                {{-- Advance Stage (hidden at final stage "inspected") --}}
                                @if($booking->fulfilment_stage !== 'inspected')
                                    @php $nextStage = app(\App\Services\FulfilmentStageService::class)->getNextStage($booking); @endphp
                                    @if($nextStage)
                                        <form method="POST" action="{{ route('admin.bookings.advance-stage', $booking) }}">
                                            @csrf
                                            <button type="submit" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200">
                                                Advance &rarr; {{ ucfirst(str_replace('_', ' ', $nextStage)) }}
                                            </button>
                                        </form>
                                    @endif
                                @endif

                                {{-- Mark Returned (only when checked_out) --}}
                                @if($booking->fulfilment_stage === 'checked_out')
                                    <form method="POST" action="{{ route('admin.bookings.mark-returned-list', $booking) }}">
                                        @csrf
                                        <button type="submit" class="text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded hover:bg-yellow-200">
                                            Mark Returned
                                        </button>
                                    </form>
                                @endif

                                {{-- Download Inspection Report (only when inspections exist) --}}
                                @if($booking->inspections_count > 0)
                                    <a href="{{ route('admin.bookings.inspection-report', $booking) }}"
                                       class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded hover:bg-purple-200">
                                        Download Inspection Report
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">No bookings found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $bookings->withQueryString()->links() }}</div>
</x-admin-layout>
