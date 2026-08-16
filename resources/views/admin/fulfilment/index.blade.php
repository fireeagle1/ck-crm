<x-admin-layout>
    <x-slot:title>Fulfilment Queue</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Fulfilment Queue</h1>
    </div>

    {{-- Stage Tabs --}}
    <div class="flex flex-wrap gap-1 mb-4 border-b">
        @php
            $stageLabels = [
                'ordered' => 'Ordered',
                'packing' => 'Packing',
                'ready' => 'Ready',
                'checked_out' => 'Checked Out',
                'returned' => 'Returned',
                'inspected' => 'Inspected',
            ];
            $stageColors = [
                'ordered' => 'bg-blue-100 text-blue-700',
                'packing' => 'bg-amber-100 text-amber-700',
                'ready' => 'bg-purple-100 text-purple-700',
                'checked_out' => 'bg-green-100 text-green-700',
                'returned' => 'bg-orange-100 text-orange-700',
                'inspected' => 'bg-gray-100 text-gray-700',
            ];
        @endphp
        @foreach ($stages as $stage)
            <a href="{{ route('admin.fulfilment.index', ['stage' => $stage, 'search' => request('search')]) }}"
               class="px-4 py-2 text-sm font-medium border-b-2 transition-colors
                   {{ $activeStage === $stage
                       ? 'border-blue-600 text-blue-600'
                       : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                {{ $stageLabels[$stage] ?? ucfirst(str_replace('_', ' ', $stage)) }}
                @if (($stageCounts[$stage] ?? 0) > 0)
                    <span class="ml-1.5 inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-xs font-semibold {{ $stageColors[$stage] ?? 'bg-gray-100 text-gray-700' }}">
                        {{ $stageCounts[$stage] }}
                    </span>
                @endif
            </a>
        @endforeach
    </div>

    {{-- Search --}}
    <div class="mb-4">
        <form method="GET" action="{{ route('admin.fulfilment.index') }}" class="flex items-center gap-2">
            <input type="hidden" name="stage" value="{{ $activeStage }}">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by customer or product name..."
                   class="w-full max-w-md rounded-md border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
            <button type="submit" class="px-4 py-2 bg-gray-100 border rounded-md text-sm font-medium hover:bg-gray-200">Search</button>
            @if(request('search'))
                <a href="{{ route('admin.fulfilment.index', ['stage' => $activeStage]) }}" class="text-sm text-gray-500 hover:text-gray-700">Clear</a>
            @endif
        </form>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
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
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Qty</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Dates</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Days in Stage</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Stage</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($bookings as $booking)
                    @php
                        $daysInStage = $booking->updated_at ? (int) $booking->updated_at->diffInDays(now()) : 0;
                        $actionLabels = [
                            'ordered' => 'Start Packing',
                            'packing' => 'Mark Ready',
                            'ready' => 'Check Out',
                            'checked_out' => 'Mark Returned',
                            'returned' => 'Inspect',
                            'inspected' => null,
                        ];
                        $actionLabel = $actionLabels[$booking->fulfilment_stage] ?? null;
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            <a href="{{ route('admin.fulfilment.show', $booking) }}" class="text-blue-600 hover:underline">#{{ $booking->id }}</a>
                        </td>
                        <td class="px-4 py-3">
                            {{ $booking->customer?->company_name ?? 'N/A' }}
                        </td>
                        <td class="px-4 py-3">
                            {{ $booking->product?->name ?? 'N/A' }}
                        </td>
                        <td class="px-4 py-3">{{ $booking->quantity }}</td>
                        <td class="px-4 py-3 text-gray-500">
                            {{ $booking->start_date->format('d M') }} – {{ $booking->end_date->format('d M Y') }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="{{ $daysInStage >= 3 ? 'text-amber-600 font-medium' : 'text-gray-500' }}">
                                {{ $daysInStage }} {{ Str::plural('day', $daysInStage) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $stageColors[$booking->fulfilment_stage] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $stageLabels[$booking->fulfilment_stage] ?? ucfirst(str_replace('_', ' ', $booking->fulfilment_stage)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($actionLabel)
                                <a href="{{ route('admin.fulfilment.show', $booking) }}"
                                   class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded-md text-xs font-medium hover:bg-blue-700 transition">
                                    {{ $actionLabel }}
                                </a>
                            @else
                                <span class="text-xs text-gray-400">Complete</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                            No bookings at the "{{ $stageLabels[$activeStage] ?? $activeStage }}" stage.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $bookings->withQueryString()->links() }}</div>
</x-admin-layout>
