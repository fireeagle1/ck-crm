<x-portal-layout>
    <x-slot:title>Order #{{ $order->id }}</x-slot:title>

    <div class="mb-4">
        <a href="{{ route('portal.orders.index') }}" class="text-blue-600 hover:underline text-sm">&larr; Back to Orders</a>
    </div>

    <h1 class="text-2xl font-semibold mb-4">Order #{{ $order->id }}</h1>

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
        </div>

        @if ($order->admin_notes)
            <div class="bg-white rounded-lg shadow-sm border p-4">
                <h2 class="text-sm font-semibold text-gray-500 uppercase mb-3">Notes</h2>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $order->admin_notes }}</p>
            </div>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <h2 class="text-sm font-semibold text-gray-500 uppercase px-4 py-3 border-b bg-gray-50">Items</h2>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Product</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Type</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Billing</th>
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
                        <td class="px-4 py-3 text-right font-medium">&pound;{{ number_format($item->price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t bg-gray-50">
                <tr>
                    <td colspan="3" class="px-4 py-3 text-right font-semibold text-gray-600">Total</td>
                    <td class="px-4 py-3 text-right font-bold">&pound;{{ number_format($order->total_amount, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</x-portal-layout>
