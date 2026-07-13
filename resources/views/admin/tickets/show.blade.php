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
                @if ($ticket->attachment_path)
                    <div class="mt-3 pt-3 border-t">
                        <a href="{{ asset('storage/' . $ticket->attachment_path) }}" target="_blank"
                           class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            Attachment
                        </a>
                    </div>
                @endif
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

                        <div>
                            <label for="ticket_type" class="block text-sm font-medium text-gray-700">Type</label>
                            <select name="ticket_type" id="ticket_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                @foreach (['Incident', 'Service Request'] as $t)
                                    <option value="{{ $t }}" {{ $ticket->ticket_type === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="user_id" class="block text-sm font-medium text-gray-700">Assigned To</label>
                            <select name="user_id" id="user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">Unassigned</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}" {{ $ticket->user_id == $u->id ? 'selected' : '' }}>
                                        {{ $u->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        </div>

                        <div>
                            <label for="asset_id" class="block text-sm font-medium text-gray-700">Linked Asset</label>
                            <select name="asset_id" id="asset_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">None</option>
                                @foreach ($assets as $asset)
                                    <option value="{{ $asset->device_id }}" {{ $ticket->asset_id == $asset->device_id ? 'selected' : '' }}>
                                        {{ $asset->device_name }} {{ $asset->device_type ? "({$asset->device_type})" : '' }}
                                    </option>
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
                    @if ($ticket->asset)
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Asset</dt>
                            <dd>
                                <a href="{{ route('admin.assets.show', $ticket->asset) }}" class="text-blue-600 hover:underline">
                                    {{ $ticket->asset->device_name }}
                                </a>
                            </dd>
                        </div>
                    @endif
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

            {{-- Activity Log --}}
            @if ($ticket->activities->isNotEmpty())
                <div class="bg-white rounded-lg shadow-sm border p-5">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Activity Log</h2>
                    <div class="space-y-3">
                        @foreach ($ticket->activities->sortByDesc('created_at') as $activity)
                            <div class="flex gap-2 text-xs">
                                <div class="flex-shrink-0 mt-0.5">
                                    <div class="h-5 w-5 rounded-full bg-gray-100 flex items-center justify-center">
                                        @if ($activity->type === 'status_changed')
                                            <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        @elseif ($activity->type === 'priority_changed')
                                            <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                        @elseif ($activity->type === 'assigned_changed')
                                            <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        @else
                                            <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <p class="text-gray-700">{{ $activity->description }}</p>
                                    <p class="text-gray-400">{{ $activity->created_at->format('M j, Y H:i') }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
