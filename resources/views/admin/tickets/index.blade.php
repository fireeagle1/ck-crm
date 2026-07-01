<x-admin-layout>
    <x-slot:title>Tickets</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Tickets</h1>
        <div class="flex gap-2">
            <a href="{{ route('admin.tickets.index', ['status' => 'open']) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium {{ $status === 'open' ? 'bg-blue-600 text-white' : 'border hover:bg-gray-50' }}">
                Open
            </a>
            <a href="{{ route('admin.tickets.index', ['status' => 'all']) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium {{ $status === 'all' ? 'bg-blue-600 text-white' : 'border hover:bg-gray-50' }}">
                All
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">ID</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Subject</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Customer</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Asset</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Priority</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($tickets as $ticket)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.tickets.show', $ticket) }}" class="text-blue-600 hover:underline">
                                INC{{ $ticket->ticket_id }}
                            </a>
                        </td>
                        <td class="px-4 py-3">{{ $ticket->subject }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $ticket->customer?->company_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $ticket->asset?->device_name ?? '—' }}</td>
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
                        <td class="px-4 py-3 text-gray-500">{{ $ticket->created_at->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">No tickets found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tickets->links() }}</div>
</x-admin-layout>
