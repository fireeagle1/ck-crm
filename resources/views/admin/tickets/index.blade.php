<x-admin-layout>
    <x-slot:title>Tickets</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Tickets</h1>
        <a href="{{ route('admin.tickets.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition">
            + Create Ticket
        </a>
    </div>

    {{-- Filters + Search --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex gap-1">
            <a href="{{ route('admin.tickets.index', ['status' => 'open', 'q' => $search]) }}"
               class="px-3 py-1.5 rounded-md text-sm font-semibold {{ $status === 'open' ? 'bg-blue-600 text-white' : 'border hover:bg-gray-50' }}">Open</a>
            <a href="{{ route('admin.tickets.index', ['status' => 'all', 'q' => $search]) }}"
               class="px-3 py-1.5 rounded-md text-sm font-semibold {{ $status === 'all' ? 'bg-blue-600 text-white' : 'border hover:bg-gray-50' }}">All</a>
        </div>
        <form method="GET" action="{{ route('admin.tickets.index') }}" class="flex gap-2 ml-auto">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search by subject, ID, or customer..."
                   class="w-64 rounded-md border-gray-300 text-sm px-3 py-1.5 focus:ring-blue-500 focus:border-blue-500">
            <button type="submit" class="px-3 py-1.5 bg-gray-100 border rounded-md text-sm hover:bg-gray-200">Search</button>
        </form>
    </div>

    <div class="bg-white rounded-lg border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">ID</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Subject</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Customer</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Priority</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Replies</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($tickets as $ticket)
                    <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('admin.tickets.show', $ticket) }}'">
                        <td class="px-4 py-3 font-medium text-blue-600">INC{{ $ticket->ticket_id }}</td>
                        <td class="px-4 py-3">
                            {{ Str::limit($ticket->subject, 40) }}
                            @if ($ticket->due_at && $ticket->due_at->isPast() && $ticket->status !== 'Closed')
                                <span class="inline-flex items-center ml-1 text-xs text-red-600 font-medium">⏰ overdue</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $ticket->customer?->company_name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $ticket->status === 'Open' ? 'bg-green-100 text-green-700' : ($ticket->status === 'Closed' ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ $ticket->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $ticket->priority === 'Critical' ? 'bg-red-100 text-red-700' : ($ticket->priority === 'High' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700') }}">
                                {{ $ticket->priority ?? 'Normal' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $ticket->replies_count }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $ticket->created_at->format('M j') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">No tickets found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tickets->appends(request()->query())->links() }}</div>
</x-admin-layout>
