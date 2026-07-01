<x-portal-layout>
    <x-slot:title>Ticket INC{{ $ticket->ticket_id }}</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">INC{{ $ticket->ticket_id }}: {{ $ticket->subject }}</h1>
        <a href="{{ route('portal.tickets.index') }}" class="text-sm text-blue-600 hover:underline">&larr; All Tickets</a>
    </div>

    <div class="max-w-3xl space-y-4">
        {{-- Status bar --}}
        <div class="bg-white rounded-lg shadow-sm border p-4 flex flex-wrap gap-4">
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

        {{-- Original description --}}
        <div class="bg-white rounded-lg shadow-sm border p-5">
            <div class="flex items-center gap-2 mb-3">
                <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-sm font-medium text-blue-700">
                    {{ strtoupper(substr($ticket->user?->first_name ?? 'U', 0, 1)) }}
                </div>
                <div>
                    <p class="text-sm font-medium">{{ $ticket->user?->full_name ?? 'You' }}</p>
                    <p class="text-xs text-gray-400">{{ $ticket->created_at->format('M j, Y \a\t H:i') }}</p>
                </div>
            </div>
            <div class="prose prose-sm max-w-none text-gray-700">
                {!! nl2br(e($ticket->description)) !!}
            </div>
        </div>

        {{-- Replies --}}
        @foreach ($ticket->replies as $reply)
            <div class="bg-white rounded-lg shadow-sm border p-5 {{ $reply->user?->isAdmin() ? 'border-l-4 border-l-blue-400' : '' }}">
                <div class="flex items-center gap-2 mb-3">
                    <div class="h-8 w-8 rounded-full {{ $reply->user?->isAdmin() ? 'bg-gray-800 text-white' : 'bg-blue-100 text-blue-700' }} flex items-center justify-center text-sm font-medium">
                        {{ strtoupper(substr($reply->user?->first_name ?? 'U', 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-sm font-medium">
                            {{ $reply->user?->full_name ?? 'Unknown' }}
                            @if ($reply->user?->isAdmin())
                                <span class="text-xs bg-blue-100 text-blue-700 rounded px-1.5 py-0.5 ml-1">Support Team</span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-400">{{ $reply->created_at->format('M j, Y \a\t H:i') }}</p>
                    </div>
                </div>
                <div class="prose prose-sm max-w-none text-gray-700">
                    {!! nl2br(e($reply->body)) !!}
                </div>
                @if ($reply->attachment_path)
                    <div class="mt-3 pt-3 border-t">
                        <a href="{{ asset('storage/' . $reply->attachment_path) }}" target="_blank"
                           class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            Attachment
                        </a>
                    </div>
                @endif
            </div>
        @endforeach

        {{-- Reply form --}}
        @if ($ticket->status !== 'Closed')
            <div class="bg-white rounded-lg shadow-sm border p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Reply</h3>
                <form method="POST" action="{{ route('portal.tickets.reply', $ticket) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="space-y-3">
                        <textarea name="body" rows="4" required placeholder="Type your message..."
                                  class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">{{ old('body') }}</textarea>
                        @error('body') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror

                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-1 text-sm text-gray-500 cursor-pointer hover:text-gray-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                <span>Attach file</span>
                                <input type="file" name="attachment" class="hidden">
                            </label>

                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                                Send Reply
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @else
            <div class="bg-gray-50 rounded-lg border p-4 text-center text-sm text-gray-500">
                This ticket is closed. Need more help? <a href="{{ route('portal.tickets.create') }}" class="text-blue-600 hover:underline">Open a new ticket</a>.
            </div>
        @endif
    </div>
</x-portal-layout>
