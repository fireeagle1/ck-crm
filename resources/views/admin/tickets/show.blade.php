<x-admin-layout>
    <x-slot:title>INC{{ $ticket->ticket_id }}</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">INC{{ $ticket->ticket_id }}: {{ $ticket->subject }}</h1>
        <a href="{{ route('admin.tickets.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Tickets</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main content --}}
        <div class="lg:col-span-2 space-y-4">
            {{-- Original description --}}
            <div class="bg-white rounded-lg shadow-sm border p-5">
                <div class="flex items-center gap-2 mb-3">
                    <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-sm font-medium text-blue-700">
                        {{ strtoupper(substr($ticket->user?->first_name ?? 'U', 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-sm font-medium">{{ $ticket->user?->full_name ?? 'Unknown' }}</p>
                        <p class="text-xs text-gray-400">{{ $ticket->created_at->format('M j, Y \a\t H:i') }}</p>
                    </div>
                </div>
                <div class="prose prose-sm max-w-none text-gray-700">
                    {!! nl2br(e($ticket->description)) !!}
                </div>
            </div>

            {{-- Replies thread --}}
            @foreach ($ticket->replies as $reply)
                <div class="bg-white rounded-lg shadow-sm border p-5 {{ $reply->is_internal ? 'border-l-4 border-l-amber-400' : '' }}">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="h-8 w-8 rounded-full {{ $reply->user?->isAdmin() ? 'bg-gray-800 text-white' : 'bg-blue-100 text-blue-700' }} flex items-center justify-center text-sm font-medium">
                            {{ strtoupper(substr($reply->user?->first_name ?? 'U', 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-sm font-medium">
                                {{ $reply->user?->full_name ?? 'Unknown' }}
                                @if ($reply->user?->isAdmin())
                                    <span class="text-xs bg-gray-200 text-gray-600 rounded px-1.5 py-0.5 ml-1">Staff</span>
                                @endif
                                @if ($reply->is_internal)
                                    <span class="text-xs bg-amber-100 text-amber-700 rounded px-1.5 py-0.5 ml-1">Internal Note</span>
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
            <div class="bg-white rounded-lg shadow-sm border p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Add Reply</h3>
                <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="space-y-3">
                        <textarea name="body" rows="4" required placeholder="Type your reply..."
                                  class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">{{ old('body') }}</textarea>
                        @error('body') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror

                        <div class="flex flex-wrap items-center gap-4">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" name="is_internal" id="is_internal" value="1"
                                       class="rounded border-gray-300 text-amber-500 shadow-sm focus:ring-amber-500">
                                <label for="is_internal" class="text-sm text-gray-600">Internal note (not visible to customer)</label>
                            </div>

                            <label class="flex items-center gap-1 text-sm text-gray-500 cursor-pointer hover:text-gray-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                <span>Attach file</span>
                                <input type="file" name="attachment" class="hidden">
                            </label>
                        </div>

                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                            Send Reply
                        </button>
                    </div>
                </form>
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
                        <dt class="text-gray-500">Replies</dt>
                        <dd>{{ $ticket->replies->count() }}</dd>
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
