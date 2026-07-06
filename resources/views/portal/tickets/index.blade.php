<x-portal-layout>
    <x-slot:title>Support Tickets</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Support Tickets</h1>
        <a href="{{ route('portal.tickets.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
            New Ticket
        </a>
    </div>

    {{-- Filters + Search --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex gap-1">
            <a href="{{ route('portal.tickets.index', ['status' => 'open', 'q' => request('q')]) }}"
               class="px-3 py-1.5 rounded-md text-sm font-semibold {{ $status === 'open' ? 'bg-blue-600 text-white' : 'border hover:bg-gray-50' }}">Open</a>
            <a href="{{ route('portal.tickets.index', ['status' => 'closed', 'q' => request('q')]) }}"
               class="px-3 py-1.5 rounded-md text-sm font-semibold {{ $status === 'closed' ? 'bg-blue-600 text-white' : 'border hover:bg-gray-50' }}">Closed</a>
            <a href="{{ route('portal.tickets.index', ['status' => 'all', 'q' => request('q')]) }}"
               class="px-3 py-1.5 rounded-md text-sm font-semibold {{ $status === 'all' ? 'bg-blue-600 text-white' : 'border hover:bg-gray-50' }}">All</a>
        </div>
        <form method="GET" action="{{ route('portal.tickets.index') }}" class="flex gap-2 ml-auto">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search by subject..."
                   class="w-56 rounded-md border-gray-300 text-sm px-3 py-1.5 focus:ring-blue-500 focus:border-blue-500">
            <button type="submit" class="px-3 py-1.5 bg-gray-100 border rounded-md text-sm hover:bg-gray-200">Search</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">ID</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Subject</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Type</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($tickets as $ticket)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('portal.tickets.show', $ticket) }}" class="text-blue-600 hover:underline font-medium">
                                INC{{ $ticket->ticket_id }}
                            </a>
                        </td>
                        <td class="px-4 py-3">{{ Str::limit($ticket->subject, 50) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $ticket->ticket_type === 'Incident' ? 'bg-red-50 text-red-700' : 'bg-purple-50 text-purple-700' }}">
                                {{ $ticket->ticket_type ?? 'Incident' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $ticket->status === 'Open' ? 'bg-green-100 text-green-700' : ($ticket->status === 'Closed' ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ $ticket->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $ticket->created_at->format('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">No tickets found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $tickets->appends(request()->query())->links() }}
    </div>
</x-portal-layout>
