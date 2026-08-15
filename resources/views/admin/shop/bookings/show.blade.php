<x-admin-layout>
    <x-slot:title>Booking #{{ $booking->id }}</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Booking #{{ $booking->id }}</h1>
        <a href="{{ route('admin.shop.bookings.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200 border">
            &larr; Back to Bookings
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Booking Details --}}
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Booking Details</h2>
                </div>
                <div class="p-4 space-y-3">
                    <div class="grid grid-cols-2 gap-y-3 text-sm">
                        <span class="text-gray-500 font-medium">Customer</span>
                        <span class="text-gray-900">{{ $booking->customer?->company_name ?? 'N/A' }}</span>

                        <span class="text-gray-500 font-medium">Product</span>
                        <span class="text-gray-900">{{ $booking->product?->name ?? 'N/A' }}</span>

                        <span class="text-gray-500 font-medium">Start Date</span>
                        <span class="text-gray-900">{{ $booking->start_date->format('d M Y') }}</span>

                        <span class="text-gray-500 font-medium">End Date</span>
                        <span class="text-gray-900">{{ $booking->end_date->format('d M Y') }}</span>

                        <span class="text-gray-500 font-medium">Quantity</span>
                        <span class="text-gray-900">{{ $booking->quantity }}</span>

                        <span class="text-gray-500 font-medium">Total Price</span>
                        <span class="text-gray-900 font-semibold">&pound;{{ number_format($booking->total_price, 2) }}</span>

                        <span class="text-gray-500 font-medium">Status</span>
                        <span>
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
                        </span>

                        @if ($booking->returned_at)
                            <span class="text-gray-500 font-medium">Returned At</span>
                            <span class="text-gray-900">{{ $booking->returned_at->format('d M Y H:i') }}</span>
                        @endif

                        <span class="text-gray-500 font-medium">Created</span>
                        <span class="text-gray-900">{{ $booking->created_at->format('d M Y H:i') }}</span>
                    </div>
                </div>
            </div>

            {{-- Linked Order --}}
            @if ($booking->orderItem && $booking->orderItem->order)
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <h2 class="text-sm font-semibold text-gray-700">Linked Order</h2>
                    </div>
                    <div class="p-4 text-sm">
                        <div class="grid grid-cols-2 gap-y-3">
                            <span class="text-gray-500 font-medium">Order</span>
                            <span>
                                <a href="{{ route('admin.shop.orders.show', $booking->orderItem->order) }}" class="text-blue-600 hover:underline font-medium">
                                    #{{ $booking->orderItem->order->id }}
                                </a>
                            </span>

                            <span class="text-gray-500 font-medium">Payment Status</span>
                            <span>
                                @php
                                    $paymentColors = [
                                        'paid' => 'bg-green-100 text-green-700',
                                        'paid_offline' => 'bg-green-100 text-green-700',
                                        'failed' => 'bg-red-100 text-red-700',
                                        'pending' => 'bg-yellow-100 text-yellow-700',
                                    ];
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $paymentColors[$booking->orderItem->order->payment_status] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ ucwords(str_replace('_', ' ', $booking->orderItem->order->payment_status)) }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Signature --}}
            @if ($booking->signature_data)
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <h2 class="text-sm font-semibold text-gray-700">Digital Signature</h2>
                    </div>
                    <div class="p-4">
                        <div class="border rounded-md p-2 bg-gray-50 inline-block">
                            <img src="data:image/png;base64,{{ $booking->signature_data }}" alt="Customer Signature" class="max-h-32">
                        </div>
                        @if ($booking->agreement_accepted_at)
                            <p class="text-xs text-gray-500 mt-2">Signed at: {{ $booking->agreement_accepted_at->format('d M Y H:i') }}</p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Agreement Text --}}
            @if ($booking->agreement_text_snapshot)
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <h2 class="text-sm font-semibold text-gray-700">Rental Agreement</h2>
                    </div>
                    <div class="p-4 prose prose-sm max-w-none text-gray-700">
                        {!! $booking->agreement_text_snapshot !!}
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Mark Returned Action --}}
            @if (in_array($booking->status, ['confirmed', 'active']))
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <h2 class="text-sm font-semibold text-gray-700">Return Action</h2>
                    </div>
                    <div class="p-4">
                        <p class="text-sm text-gray-500 mb-4">Mark this booking as returned. The customer will be notified by email.</p>
                        <form method="POST" action="{{ route('admin.shop.bookings.markReturned', $booking) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                    onclick="return confirm('Are you sure you want to mark this booking as returned?')"
                                    class="w-full inline-flex items-center justify-center px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700 transition">
                                Mark as Returned
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Booking Summary --}}
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Summary</h2>
                </div>
                <div class="p-4 space-y-3 text-sm">
                    @php
                        $days = $booking->start_date->diffInDays($booking->end_date) + 1;
                    @endphp
                    <div class="flex justify-between">
                        <span class="text-gray-500">Duration</span>
                        <span class="text-gray-900 font-medium">{{ $days }} {{ Str::plural('day', $days) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Quantity</span>
                        <span class="text-gray-900 font-medium">{{ $booking->quantity }} {{ Str::plural('unit', $booking->quantity) }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-3">
                        <span class="text-gray-700 font-medium">Total</span>
                        <span class="text-gray-900 font-semibold">&pound;{{ number_format($booking->total_price, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
