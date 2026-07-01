<x-admin-layout>
    <x-slot:title>INC{{ $ticket->ticket_id }}</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">INC{{ $ticket->ticket_id }}: {{ $ticket->subject }}</h1>
        <a href="{{ route('admin.tickets.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Tickets</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main content --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-2">Description</h2>
                <div class="prose prose-sm max-w-none text-gray-700">
                    {!! nl2br(e($ticket->description)) !!}
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            {{-- Update form --}}
            <div class="bg-white rounded-lg shadow-sm border p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Update Ticket</h2>
                <form method="POST" action="{{ route('admin.tickets.update', $ticket) }}">
                    @csrf
                    @method('PUT')

                    <div class="space-y-3">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                @foreach (['Open', 'Pending', 'In Progress', 'Closed'] as $s)
                                    <option value="{{ $s }}" {{ $ticket->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="priority" class="block text-sm font-medium text-gray-700">Priority</label>
                            <select name="priority" id="priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                @foreach (['Low', 'Normal', 'High', 'Critical'] as $p)
                                    <option value="{{ $p }}" {{ $ticket->priority === $p ? 'selected' : '' }}>{{ $p }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                            Update
                        </button>
                    </div>
                </form>
            </div>

            {{-- Details --}}
            <div class="bg-white rounded-lg shadow-sm border p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Details</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Customer</dt>
                        <dd>
                            @if ($ticket->customer)
                                <a href="{{ route('admin.customers.show', $ticket->customer) }}" class="text-blue-600 hover:underline">
                                    {{ $ticket->customer->company_name }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Raised by</dt>
                        <dd>{{ $ticket->user?->full_name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Created</dt>
                        <dd>{{ $ticket->created_at->format('M j, Y H:i') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Updated</dt>
                        <dd>{{ $ticket->updated_at->format('M j, Y H:i') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</x-admin-layout>
