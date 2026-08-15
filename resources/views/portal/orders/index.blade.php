<x-portal-layout>
    <x-slot:title>Orders</x-slot:title>

    <h1 class="text-2xl font-semibold mb-4">Order History</h1>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Order #</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Date</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Products</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Type</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Total</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($orders as $order)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">#{{ $order->id }}</td>
                        <td class="px-4 py-3">{{ $order->created_at->format('M j, Y') }}</td>
                        <td class="px-4 py-3">
                            @foreach ($order->items as $item)
                                <span class="block">{{ $item->product_name }}</span>
                            @endforeach
                        </td>
                        <td class="px-4 py-3">
                            @foreach ($order->items as $item)
                                <span class="block">
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
                                </span>
                            @endforeach
                        </td>
                        <td class="px-4 py-3 font-medium">&pound;{{ number_format($order->total_amount, 2) }}</td>
                        <td class="px-4 py-3">
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
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('portal.orders.show', $order) }}" class="text-blue-600 hover:underline text-sm">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">No orders yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
</x-portal-layout>
