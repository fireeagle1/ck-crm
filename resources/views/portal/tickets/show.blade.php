<x-portal-layout>
    <x-slot:title>INC{{ $ticket->ticket_id }}</x-slot:title>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">INC{{ $ticket->ticket_id }}: {{ $ticket->subject }}</h1>
        </div>
        <a href="{{ route('portal.tickets.index') }}" class="text-sm text-blue-600 hover:underline font-medium">&larr; All Tickets</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main: conversation --}}
        <div class="lg:col-span-2 space-y-4">
            {{-- Original description --}}
            <div class="bg-white rounded-lg border p-5">
                <div class="flex items-center gap-2 mb-3">
                    <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-sm font-bold text-blue-700">
                        {{ strtoupper(substr($ticket->user?->first_name ?? 'U', 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-sm font-semibold">{{ $ticket->user?->full_name ?? 'You' }}</p>
                        <p class="text-xs text-gray-400">{{ $ticket->created_at->format('M j, Y \a\t H:i') }}</p>
                    </div>
                </div>
                <div class="prose prose-sm max-w-none text-gray-700">
                    {!! nl2br(e($ticket->description)) !!}
                </div>
            </div>

            {{-- Replies --}}
            @foreach ($ticket->replies as $reply)
                <div class="bg-white rounded-lg border p-5 {{ $reply->user?->isAdmin() ? 'border-l-4 border-l-blue-400' : '' }}">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="h-8 w-8 rounded-full {{ $reply->user?->isAdmin() ? 'bg-slate-800 text-white' : 'bg-blue-100 text-blue-700' }} flex items-center justify-center text-sm font-bold">
                            {{ strtoupper(substr($reply->user?->first_name ?? 'U', 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-sm font-semibold">
                                {{ $reply->user?->full_name ?? 'Unknown' }}
                                @if ($reply->user?->isAdmin())
                                    <span class="text-xs bg-blue-100 text-blue-700 rounded px-1.5 py-0.5 ml-1 font-medium">Support Team</span>
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
                <div class="bg-white rounded-lg border p-5">
                    <h3 class="text-sm font-bold text-gray-700 mb-3">Reply</h3>
                    <form method="POST" action="{{ route('portal.tickets.reply', $ticket) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="space-y-3">
                            <textarea name="body" rows="4" required placeholder="Type your message..."
                                      class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">{{ old('body') }}</textarea>
                            @error('body') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror

                            <div class="flex items-center justify-between">
                                <label class="flex items-center gap-1 text-sm text-gray-500 cursor-pointer hover:text-gray-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    <span>Attach file</span>
                                    <input type="file" name="attachment" class="hidden">
                                </label>

                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition">
                                    Send Reply
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            @else
                <div class="bg-gray-50 rounded-lg border p-4 text-center text-sm text-gray-500">
                    This ticket is closed. <a href="{{ route('portal.tickets.create') }}" class="text-blue-600 hover:underline font-medium">Open a new ticket</a> if you need further help.
                </div>
            @endif
        </div>

        {{-- Sidebar: Ticket metadata --}}
        <div class="space-y-4">
            <div class="bg-white rounded-lg border p-5">
                <h2 class="font-bold text-sm text-gray-700 mb-3">Ticket Details</h2>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-gray-500">Status</dt>
                        <dd class="mt-0.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                {{ $ticket->status === 'Open' ? 'bg-green-100 text-green-700' : ($ticket->status === 'Closed' ? 'bg-gray-100 text-gray-600' : 'bg-amber-100 text-amber-700') }}">
                                {{ $ticket->status }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Priority</dt>
                        <dd class="mt-0.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                {{ $ticket->priority === 'Critical' ? 'bg-red-100 text-red-700' : ($ticket->priority === 'High' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700') }}">
                                {{ $ticket->priority ?? 'Normal' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Assigned To</dt>
                        <dd class="mt-0.5 font-medium">{{ $agent?->full_name ?? 'Support Team' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Opened</dt>
                        <dd class="mt-0.5">{{ $ticket->created_at->format('M j, Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Last Updated</dt>
                        <dd class="mt-0.5">{{ $ticket->updated_at->diffForHumans() }}</dd>
                    </div>
                    @if ($ticket->service)
                        <div>
                            <dt class="text-gray-500">Service</dt>
                            <dd class="mt-0.5">
                                <a href="{{ route('portal.services.show', $ticket->service) }}" class="text-blue-600 hover:underline">
                                    {{ $ticket->service->service_short }}
                                </a>
                            </dd>
                        </div>
                    @endif
                    @if ($ticket->asset)
                        <div>
                            <dt class="text-gray-500">Linked Asset</dt>
                            <dd class="mt-0.5 font-medium">{{ $ticket->asset->device_name }}</dd>
                            @if ($ticket->asset->device_type)
                                <dd class="text-xs text-gray-400">{{ $ticket->asset->device_type }}</dd>
                            @endif
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Close ticket button --}}
            @if ($ticket->status !== 'Closed')
                <div class="bg-white rounded-lg border p-5">
                    <p class="text-sm text-gray-500 mb-3">If your issue is resolved, you can close this ticket.</p>
                    <form method="POST" action="{{ route('portal.tickets.close', $ticket) }}">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm font-semibold hover:bg-gray-50 transition"
                                onclick="return confirm('Close this ticket?')">
                            Close Ticket
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-portal-layout>
