<x-admin-layout>
    <x-slot:title>Booking Calendar</x-slot:title>

    @php
        $prevMonth = $month - 1;
        $prevYear = $year;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
        $nextMonth = $month + 1;
        $nextYear = $year;
        if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
    @endphp

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Booking Calendar</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.shop.bookings.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200 border">
                &larr; List View
            </a>
            <button onclick="document.getElementById('blockDatesModal').classList.remove('hidden')"
                    class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700 transition">
                Block Dates
            </button>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Month Navigation --}}
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('admin.shop.bookings.calendar', ['month' => $prevMonth, 'year' => $prevYear]) }}"
           class="inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200 border">
            &larr; Previous
        </a>
        <h2 class="text-lg font-semibold text-gray-800">
            {{ $startOfMonth->format('F Y') }}
        </h2>
        <a href="{{ route('admin.shop.bookings.calendar', ['month' => $nextMonth, 'year' => $nextYear]) }}"
           class="inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200 border">
            Next &rarr;
        </a>
    </div>

    {{-- Legend --}}
    <div class="flex items-center gap-4 mb-4 text-xs">
        <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-green-500 inline-block"></span> Confirmed</span>
        <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-500 inline-block"></span> Active</span>
        <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-gray-400 inline-block"></span> Returned</span>
        <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-500 inline-block"></span> Cancelled</span>
        <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded inline-block" style="background: repeating-linear-gradient(45deg, #dc2626, #dc2626 2px, #fca5a5 2px, #fca5a5 4px);"></span> Blocked</span>
    </div>

    {{-- Calendar Grid --}}
    <div class="bg-white rounded-lg shadow-sm border overflow-x-auto">
        <table class="w-full text-sm border-collapse" style="min-width: {{ 200 + ($daysInMonth * 36) }}px;">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold text-gray-600 sticky left-0 bg-gray-50 z-10 border-r min-w-[180px]">Product</th>
                    @for ($day = 1; $day <= $daysInMonth; $day++)
                        @php
                            $date = $startOfMonth->copy()->day($day);
                            $isWeekend = $date->isWeekend();
                            $isToday = $date->isToday();
                        @endphp
                        <th class="px-0.5 py-2 text-center font-medium text-xs w-8 {{ $isWeekend ? 'bg-gray-100' : '' }} {{ $isToday ? 'bg-blue-50 text-blue-700' : 'text-gray-500' }}">
                            <div>{{ $date->format('D') }}</div>
                            <div class="{{ $isToday ? 'font-bold' : '' }}">{{ $day }}</div>
                        </th>
                    @endfor
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($products as $product)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-3 py-2 font-medium text-gray-900 sticky left-0 bg-white z-10 border-r whitespace-nowrap">
                            {{ $product->name }}
                            <span class="text-xs text-gray-400 ml-1">({{ $product->stock_quantity ?? 1 }} units)</span>
                        </td>
                        @for ($day = 1; $day <= $daysInMonth; $day++)
                            @php
                                $date = $startOfMonth->copy()->day($day);
                                $isWeekend = $date->isWeekend();
                                $cellBookings = collect();
                                if (isset($bookingsByProduct[$product->id])) {
                                    $cellBookings = $bookingsByProduct[$product->id]->filter(function ($b) use ($date) {
                                        return $b->start_date->lte($date) && $b->end_date->gte($date);
                                    });
                                }
                            @endphp
                            <td class="px-0 py-1 text-center relative {{ $isWeekend ? 'bg-gray-50' : '' }}"
                                @if($cellBookings->isEmpty())
                                    data-product-id="{{ $product->id }}"
                                    data-date="{{ $date->format('Y-m-d') }}"
                                    onclick="handleEmptyClick(this)"
                                    style="cursor: pointer;"
                                @endif
                            >
                                @foreach ($cellBookings as $booking)
                                    @php
                                        $isBlocked = is_null($booking->company_id);
                                        $isStart = $booking->start_date->eq($date);
                                        $statusColors = [
                                            'confirmed' => 'bg-green-500',
                                            'active' => 'bg-blue-500',
                                            'returned' => 'bg-gray-400',
                                            'cancelled' => 'bg-red-500',
                                        ];
                                        $color = $isBlocked ? '' : ($statusColors[$booking->status] ?? 'bg-gray-400');
                                        $customerName = $isBlocked ? 'BLOCKED' : ($booking->customer?->company_name ?? 'N/A');
                                        $roundStart = $isStart ? 'rounded-l' : '';
                                        $isEnd = $booking->end_date->eq($date);
                                        $roundEnd = $isEnd ? 'rounded-r' : '';
                                    @endphp
                                    <div class="h-5 {{ $color }} {{ $roundStart }} {{ $roundEnd }} opacity-90 hover:opacity-100 transition-opacity"
                                         @if($isBlocked)
                                             style="background: repeating-linear-gradient(45deg, #dc2626, #dc2626 2px, #fca5a5 2px, #fca5a5 4px); cursor: pointer;"
                                             onclick="openEditBlock({{ $booking->id }}, '{{ $booking->product->name ?? 'Product' }}', '{{ $booking->start_date->format('Y-m-d') }}', '{{ $booking->end_date->format('Y-m-d') }}', '{{ addslashes($booking->block_reason ?? '') }}')"
                                         @endif
                                         title="{{ $customerName }}{{ $isBlocked && $booking->block_reason ? ' — ' . $booking->block_reason : '' }} | {{ $booking->start_date->format('d M') }} - {{ $booking->end_date->format('d M') }} ({{ ucfirst($booking->status) }})"
                                         @if(!$isBlocked)
                                             onclick="window.location.href='{{ route('admin.shop.bookings.show', $booking) }}'"
                                             style="cursor: pointer;"
                                         @endif
                                    ></div>
                                @endforeach
                                @if($cellBookings->isEmpty())
                                    <div class="h-5"></div>
                                @endif
                            </td>
                        @endfor
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-xs text-gray-500">Click on an empty cell to create a new booking. Click on a blocked bar to edit or remove it. Hover over bars for details.</p>

    {{-- Block Dates Modal --}}
    <div id="blockDatesModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="document.getElementById('blockDatesModal').classList.add('hidden')"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full z-10">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Block Dates</h3>
                    <p class="text-sm text-gray-500 mt-1">Mark dates as unavailable for a product.</p>
                </div>
                <form method="POST" action="{{ route('admin.shop.bookings.blockDates') }}" class="p-6 space-y-4">
                    @csrf
                    <div>
                        <label for="block_product_id" class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                        <select name="product_id" id="block_product_id" required
                                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select a product...</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="block_start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" name="start_date" id="block_start_date" required
                                   min="{{ now()->format('Y-m-d') }}"
                                   class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="block_end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" name="end_date" id="block_end_date" required
                                   class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                    <div>
                        <label for="block_reason" class="block text-sm font-medium text-gray-700 mb-1">Reason (optional)</label>
                        <input type="text" name="reason" id="block_reason" placeholder="e.g. Maintenance, Reserved"
                               class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" onclick="document.getElementById('blockDatesModal').classList.add('hidden')"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200 border">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700 transition">
                            Block Dates
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- JavaScript for empty cell click --}}
    <script>
        function handleEmptyClick(cell) {
            const productId = cell.getAttribute('data-product-id');
            const date = cell.getAttribute('data-date');
            const url = new URL('{{ route('admin.shop.bookings.create') }}', window.location.origin);
            url.searchParams.set('product_id', productId);
            url.searchParams.set('start_date', date);
            window.location.href = url.toString();
        }

        function openEditBlock(bookingId, productName, startDate, endDate, reason) {
            document.getElementById('edit_block_product_name').textContent = productName;
            document.getElementById('edit_block_start_date').value = startDate;
            document.getElementById('edit_block_end_date').value = endDate;
            document.getElementById('edit_block_reason').value = reason || '';

            // Set form actions
            const updateForm = document.getElementById('editBlockForm');
            updateForm.action = '{{ url("admin/shop/bookings") }}/' + bookingId + '/block';

            const deleteForm = document.getElementById('deleteBlockForm');
            deleteForm.action = '{{ url("admin/shop/bookings") }}/' + bookingId + '/block';

            document.getElementById('editBlockModal').classList.remove('hidden');
        }
    </script>

    {{-- Edit Block Modal --}}
    <div id="editBlockModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="document.getElementById('editBlockModal').classList.add('hidden')"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full z-10">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Edit Blocked Dates</h3>
                    <p class="text-sm text-gray-500 mt-1">Product: <span id="edit_block_product_name" class="font-medium text-gray-700"></span></p>
                </div>
                <form method="POST" id="editBlockForm" class="p-6 space-y-4">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="edit_block_start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" name="start_date" id="edit_block_start_date" required
                                   class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="edit_block_end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" name="end_date" id="edit_block_end_date" required
                                   class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                    <div>
                        <label for="edit_block_reason" class="block text-sm font-medium text-gray-700 mb-1">Reason (optional)</label>
                        <input type="text" name="reason" id="edit_block_reason" placeholder="e.g. Maintenance, Reserved"
                               class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" onclick="document.getElementById('editBlockModal').classList.add('hidden')"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200 border">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition">
                            Update Block
                        </button>
                    </div>
                </form>
                <div class="px-6 pb-6">
                    <form method="POST" id="deleteBlockForm">
                        @csrf
                        @method('DELETE')
                        <button type="submit" onclick="return confirm('Are you sure you want to remove this block? The dates will become available again.')"
                                class="w-full px-4 py-2 bg-red-100 text-red-700 rounded-md text-sm font-medium hover:bg-red-200 border border-red-200 transition">
                            Remove Block
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
