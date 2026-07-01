<x-portal-layout>
    <x-slot:title>Ticket INC{{ $ticket->ticket_id }}</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">INC{{ $ticket->ticket_id }}: {{ $ticket->subject }}</h1>
        <a href="{{ route('portal.tickets.index') }}" class="text-sm text-blue-600 hover:underline">&larr; All Tickets</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-3xl">
        {{-- Status & Priority --}}
        <div class="flex flex-wrap gap-4 mb-6">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Status</p>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium mt-1
                    {{ $ticket->status === 'Open' ? 'bg-green-100 text-green-700' : ($ticket->status === 'Closed' ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-700') }}">
                    {{ $ticket->status }}
                </span>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Priority</p>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium mt-1
                    {{ $ticket->priority === 'Critical' ? 'bg-red-100 text-red-700' : ($ticket->priority === 'High' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700') }}">
                    {{ $ticket->priority ?? 'Normal' }}
                </span>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Opened</p>
                <p class="text-sm mt-1">{{ $ticket->created_at->format('M j, Y \a\t H:i') }}</p>
            </div>
        </div>

        {{-- Description --}}
        <div class="border-t pt-4">
            <h2 class="text-sm font-semibold text-gray-700 mb-2">Description</h2>
            <div class="prose prose-sm max-w-none text-gray-700">
                {!! nl2br(e($ticket->description)) !!}
            </div>
        </div>
    </div>
</x-portal-layout>
