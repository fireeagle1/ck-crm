<x-portal-layout>
    <x-slot:title>Order #{{ $order->id }}</x-slot:title>

    <div class="mb-4">
        <a href="{{ route('portal.orders.index') }}" class="text-blue-600 hover:underline text-sm">&larr; Back to Orders</a>
    </div>

    <h1 class="text-2xl font-semibold mb-4">Order #{{ $order->id }}</h1>

    @if (session('error'))
        <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <h2 class="text-sm font-semibold text-gray-500 uppercase mb-3">Order Details</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Date</dt>
                    <dd class="font-medium">{{ $order->created_at->format('M j, Y \a\t g:ia') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Total</dt>
                    <dd class="font-medium">&pound;{{ number_format($order->total_amount, 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Payment Status</dt>
                    <dd>
                        @switch($order->payment_status)
                            @case('paid')
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Paid</span>
                                @break
                            @case('paid_offline')
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Paid</span>
                                @break
                            @case('pending')
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>
                                @break
                            @case('failed')
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700">Failed</span>
                                @break
                        @endswitch
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Fulfilment Status</dt>
                    <dd>
                        @switch($order->fulfilment_status)
                            @case('pending')
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>
                                @break
                            @case('awaiting_fulfilment')
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-orange-100 text-orange-700">Awaiting Fulfilment</span>
                                @break
                            @case('completed')
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Completed</span>
                                @break
                        @endswitch
                    </dd>
                </div>
                @if ($order->fulfilled_at)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Fulfilled</dt>
                        <dd class="font-medium">{{ $order->fulfilled_at->format('M j, Y') }}</dd>
                    </div>
                @endif
                @if ($order->stripe_checkout_session_id)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Payment Reference</dt>
                        <dd class="font-mono text-xs text-gray-600">{{ $order->stripe_checkout_session_id }}</dd>
                    </div>
                @endif
            </dl>

            {{-- PDF Invoice Download --}}
            @if ($order->invoice_pdf_path)
                <div class="mt-4 pt-4 border-t">
                    <a href="{{ route('portal.orders.downloadInvoice', $order) }}"
                       class="inline-flex items-center gap-2 min-w-[44px] min-h-[44px] px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Download Invoice PDF
                    </a>
                </div>
            @endif
        </div>

        {{-- Delivery Address --}}
        @if ($order->delivery_address_line1)
            <div class="bg-white rounded-lg shadow-sm border p-4">
                <h2 class="text-sm font-semibold text-gray-500 uppercase mb-3">Delivery Address</h2>
                <div class="text-sm text-gray-700">
                    <p>{{ $order->delivery_address_line1 }}</p>
                    @if ($order->delivery_address_line2)
                        <p>{{ $order->delivery_address_line2 }}</p>
                    @endif
                    <p>
                        {{ $order->delivery_city }}@if ($order->delivery_state), {{ $order->delivery_state }}@endif
                    </p>
                    <p>{{ $order->delivery_postal_code }}</p>
                    <p>{{ $order->delivery_country }}</p>
                </div>
            </div>
        @elseif ($order->admin_notes)
            <div class="bg-white rounded-lg shadow-sm border p-4">
                <h2 class="text-sm font-semibold text-gray-500 uppercase mb-3">Notes</h2>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $order->admin_notes }}</p>
            </div>
        @endif
    </div>

    {{-- Order Items --}}
    <div class="bg-white rounded-lg shadow-sm border overflow-hidden mb-6">
        <h2 class="text-sm font-semibold text-gray-500 uppercase px-4 py-3 border-b bg-gray-50">Items</h2>
        <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Product</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Type</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Billing</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-600">Qty</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Price</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($order->items as $item)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $item->product_name }}</td>
                        <td class="px-4 py-3">
                            @switch($item->product_type)
                                @case('one_off')
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700">One-Off</span>
                                    @break
                                @case('equipment_rental')
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-purple-100 text-purple-700">Rental</span>
                                    @break
                                @case('hosting')
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-indigo-100 text-indigo-700">Hosting</span>
                                    @break
                            @endswitch
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $item->billing_frequency ? ucfirst($item->billing_frequency) : '—' }}</td>
                        <td class="px-4 py-3 text-center text-gray-700">
                            @if ($item->product_type === 'one_off' && $item->quantity > 1)
                                &times;{{ $item->quantity }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-medium">
                            @if ($item->product_type === 'one_off' && $item->quantity > 1)
                                <span class="text-gray-500 text-xs">&pound;{{ number_format($item->price, 2) }} &times; {{ $item->quantity }}</span>
                                <br>&pound;{{ number_format($item->price * $item->quantity, 2) }}
                            @else
                                &pound;{{ number_format($item->price, 2) }}
                            @endif
                        </td>
                    </tr>
                    {{-- Delivery instructions for this item --}}
                    @if ($item->product && $item->product->delivery_instructions)
                        <tr class="bg-blue-50">
                            <td colspan="5" class="px-4 py-2">
                                <div class="flex items-start gap-2 text-xs text-blue-700">
                                    <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span><strong>Delivery/Collection:</strong> {{ $item->product->delivery_instructions }}</span>
                                </div>
                            </td>
                        </tr>
                    @endif

                    {{-- Rental booking details (Req 8.1, 10.6) --}}
                    @if ($item->product_type === 'equipment_rental' && $item->booking)
                        <tr>
                            <td colspan="5" class="px-4 py-3">
                                @include('portal.orders.partials.booking-details', ['booking' => $item->booking])
                            </td>
                        </tr>
                    @endif

                    {{-- Question answers (Req 11.3) --}}
                    @if ($item->questionAnswers && $item->questionAnswers->isNotEmpty())
                        <tr>
                            <td colspan="5" class="px-4 py-3">
                                @include('portal.orders.partials.question-answers', ['answers' => $item->questionAnswers])
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
            <tfoot class="border-t bg-gray-50">
                <tr>
                    <td colspan="4" class="px-4 py-3 text-right font-semibold text-gray-600">Total</td>
                    <td class="px-4 py-3 text-right font-bold">&pound;{{ number_format($order->total_amount, 2) }}</td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>

</x-portal-layout>
