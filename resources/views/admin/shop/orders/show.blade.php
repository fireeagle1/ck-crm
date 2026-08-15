<x-admin-layout>
    <x-slot:title>Order #{{ $order->id }}</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Order #{{ $order->id }}</h1>
        <a href="{{ route('admin.shop.orders.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200 border">
            &larr; Back to Orders
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
            {{-- Order Details --}}
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Order Details</h2>
                </div>
                <div class="p-4 space-y-3">
                    <div class="grid grid-cols-2 gap-y-3 text-sm">
                        <span class="text-gray-500 font-medium">Customer</span>
                        <span class="text-gray-900">{{ $order->customer?->company_name ?? 'N/A' }}</span>

                        <span class="text-gray-500 font-medium">Payment Status</span>
                        <span>
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
                        </span>

                        <span class="text-gray-500 font-medium">Fulfilment Status</span>
                        <span>
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
                        </span>

                        <span class="text-gray-500 font-medium">Total Amount</span>
                        <span class="text-gray-900 font-semibold">&pound;{{ number_format($order->total_amount, 2) }}</span>

                        <span class="text-gray-500 font-medium">Order Date</span>
                        <span class="text-gray-900">{{ $order->created_at->format('d M Y H:i') }}</span>

                        @if ($order->fulfilled_at)
                            <span class="text-gray-500 font-medium">Fulfilled At</span>
                            <span class="text-gray-900">{{ $order->fulfilled_at->format('d M Y H:i') }}</span>
                        @endif

                        @if ($order->stripe_checkout_session_id)
                            <span class="text-gray-500 font-medium">Stripe Checkout Session</span>
                            <span><code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded text-gray-700">{{ $order->stripe_checkout_session_id }}</code></span>
                        @endif

                        @if ($order->stripe_payment_intent_id)
                            <span class="text-gray-500 font-medium">Stripe Payment Intent</span>
                            <span><code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded text-gray-700">{{ $order->stripe_payment_intent_id }}</code></span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Order Items --}}
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Items</h2>
                </div>
                <table class="min-w-full text-sm">
                    <thead class="border-b">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Product</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Type</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Price</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Billing</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Service</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($order->items as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $item->product_name }}</td>
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
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $typeColors[$item->product_type] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ $typeLabels[$item->product_type] ?? ucwords(str_replace('_', ' ', $item->product_type)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">&pound;{{ number_format($item->price, 2) }}</td>
                                <td class="px-4 py-3 text-gray-500">
                                    {{ $item->billing_frequency ? ucfirst($item->billing_frequency) : 'One-off' }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($item->service)
                                        @php
                                            $serviceColors = [
                                                'Active' => 'bg-green-100 text-green-700',
                                                'active' => 'bg-green-100 text-green-700',
                                                'pending' => 'bg-yellow-100 text-yellow-700',
                                                'Pending' => 'bg-yellow-100 text-yellow-700',
                                                'cancelled' => 'bg-red-100 text-red-700',
                                                'Cancelled' => 'bg-red-100 text-red-700',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $serviceColors[$item->service->status] ?? 'bg-gray-100 text-gray-700' }}">
                                            {{ ucfirst($item->service->status) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Fulfilment Action --}}
            @if ($order->fulfilment_status !== 'completed')
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div class="px-4 py-3 border-b bg-gray-50">
                        <h2 class="text-sm font-semibold text-gray-700">Fulfilment</h2>
                    </div>
                    <div class="p-4">
                        <p class="text-sm text-gray-500 mb-4">Mark this order as fulfilled. This will set the fulfilment status to completed and activate any pending services.</p>
                        <form method="POST" action="{{ route('admin.shop.orders.fulfil', $order) }}">
                            @csrf
                            <button type="submit"
                                    onclick="return confirm('Are you sure you want to mark this order as fulfilled?')"
                                    class="w-full inline-flex items-center justify-center px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700 transition">
                                Mark as Fulfilled
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Admin Notes --}}
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Admin Notes</h2>
                </div>
                <div class="p-4">
                    @if ($order->admin_notes)
                        <div class="mb-4 p-3 bg-gray-50 rounded-md text-sm text-gray-700 whitespace-pre-wrap border">{{ $order->admin_notes }}</div>
                    @else
                        <p class="text-sm text-gray-400 mb-4">No notes yet.</p>
                    @endif

                    <form method="POST" action="{{ route('admin.shop.orders.note', $order) }}">
                        @csrf
                        <div class="mb-3">
                            <textarea name="note" rows="3" required maxlength="1000"
                                      class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('note') border-red-300 @enderror"
                                      placeholder="Add a note...">{{ old('note') }}</textarea>
                            @error('note')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition">
                            Add Note
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
