<x-portal-layout>
    <x-slot:title>{{ $project->title }}</x-slot:title>

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">{{ $project->title }}</h1>
        </div>
        <a href="{{ route('portal.projects.index') }}" class="text-sm text-blue-600 hover:underline font-medium">&larr; All Projects</a>
    </div>

    {{-- Status & Progress --}}
    <div class="bg-white rounded-lg border p-6 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                @php
                    $statusClasses = match($project->status) {
                        'Not Started' => 'bg-gray-100 text-gray-700',
                        'In Progress' => 'bg-blue-100 text-blue-700',
                        'On Hold' => 'bg-yellow-100 text-yellow-700',
                        'Awaiting Approval' => 'bg-purple-100 text-purple-700',
                        'Completed' => 'bg-green-100 text-green-700',
                        default => 'bg-gray-100 text-gray-700',
                    };
                @endphp
                <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $statusClasses }}">
                    {{ $project->status }}
                </span>
            </div>
            <span class="text-sm font-medium text-gray-600">{{ $project->progress_percentage }}% complete</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2.5 mt-3">
            <div class="bg-green-500 h-2.5 rounded-full" style="width: {{ $project->progress_percentage }}%"></div>
        </div>
    </div>

    <div class="space-y-6">
        {{-- Tasks --}}
        <div class="bg-white rounded-lg border p-6">
            <h2 class="font-bold text-lg mb-4">Tasks</h2>
            @forelse ($project->tasks as $task)
                <div class="flex items-center gap-3 py-2 {{ !$loop->last ? 'border-b' : '' }}">
                    @if ($task->status === 'Done')
                        <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    @elseif ($task->status === 'In Progress')
                        <svg class="w-5 h-5 text-blue-500 shrink-0 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    @else
                        <svg class="w-5 h-5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <circle cx="12" cy="12" r="9"/>
                        </svg>
                    @endif
                    <span class="text-sm {{ $task->status === 'Done' ? 'text-gray-500 line-through' : 'text-gray-900' }}">{{ $task->title }}</span>
                    <span class="ml-auto text-xs font-medium {{ $task->status === 'Done' ? 'text-green-600' : ($task->status === 'In Progress' ? 'text-blue-600' : 'text-gray-400') }}">
                        {{ $task->status }}
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-500">No tasks have been added to this project yet.</p>
            @endforelse
        </div>

        {{-- Comments --}}
        <div class="bg-white rounded-lg border p-6">
            <h2 class="font-bold text-lg mb-4">Comments</h2>
            @forelse ($project->comments as $comment)
                <div class="py-3 {{ !$loop->last ? 'border-b' : '' }}">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-sm font-semibold text-gray-900">{{ $comment->user->name ?? 'Unknown' }}</span>
                        <span class="text-xs text-gray-400">{{ $comment->created_at->format('M j, Y \a\t g:ia') }}</span>
                    </div>
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ $comment->body }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500">No comments yet.</p>
            @endforelse
        </div>

        {{-- Documents --}}
        <div class="bg-white rounded-lg border p-6">
            <h2 class="font-bold text-lg mb-4">Documents</h2>
            @forelse ($project->documents as $document)
                <div class="flex items-center justify-between py-2 {{ !$loop->last ? 'border-b' : '' }}">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $document->label }}</p>
                        <p class="text-xs text-gray-500">{{ $document->document_type }} &middot; Uploaded {{ $document->created_at->format('M j, Y') }}</p>
                    </div>
                    <a href="{{ route('portal.projects.documents.download', [$project, $document]) }}" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download
                    </a>
                </div>
            @empty
                <p class="text-sm text-gray-500">No documents have been shared yet.</p>
            @endforelse
        </div>

        {{-- Decisions --}}
        <div class="bg-white rounded-lg border p-6">
            <h2 class="font-bold text-lg mb-4">Decisions</h2>
            @forelse ($project->decisions as $decision)
                <div class="py-3 {{ !$loop->last ? 'border-b' : '' }}">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-sm font-semibold text-gray-900">{{ $decision->title }}</span>
                        @if ($decision->category)
                            @php
                                $categoryClasses = match($decision->category) {
                                    'Design Requirement' => 'bg-pink-100 text-pink-700',
                                    'Client Decision' => 'bg-indigo-100 text-indigo-700',
                                    'Technical Decision' => 'bg-teal-100 text-teal-700',
                                    default => 'bg-gray-100 text-gray-700',
                                };
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $categoryClasses }}">
                                {{ $decision->category }}
                            </span>
                        @endif
                    </div>
                    @if ($decision->description)
                        <p class="text-sm text-gray-700 mt-1">{{ $decision->description }}</p>
                    @endif
                    <p class="text-xs text-gray-400 mt-1">Recorded {{ $decision->date_recorded->format('M j, Y') }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500">No decisions recorded yet.</p>
            @endforelse
        </div>

        {{-- Pending Approvals --}}
        @if ($project->approvalRequests->isNotEmpty())
            <div class="bg-white rounded-lg border p-6">
                <h2 class="font-bold text-lg mb-4">Pending Approvals</h2>
                @foreach ($project->approvalRequests as $approval)
                    <div class="py-4 {{ !$loop->last ? 'border-b' : '' }}">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-purple-100 text-purple-700">
                                {{ $approval->type }}
                            </span>
                            @if ($approval->type === 'Document Approval' && $approval->document)
                                <span class="text-sm text-gray-600">— {{ $approval->document->label }}</span>
                            @endif
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3">
                            {{-- Approve button --}}
                            <form method="POST" action="{{ route('portal.projects.approvals.approve', [$project, $approval]) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1 px-4 py-2 bg-green-600 text-white rounded-md text-sm font-semibold hover:bg-green-700 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Approve
                                </button>
                            </form>

                            {{-- Reject form --}}
                            <form method="POST" action="{{ route('portal.projects.approvals.reject', [$project, $approval]) }}" class="flex-1">
                                @csrf
                                <div class="flex flex-col gap-2">
                                    <textarea
                                        name="reason"
                                        rows="2"
                                        placeholder="Reason for rejection{{ $approval->type === 'Project Completion' ? ' (required)' : ' (optional)' }}"
                                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        {{ $approval->type === 'Project Completion' ? 'required' : '' }}
                                    ></textarea>
                                    <button type="submit" class="self-start inline-flex items-center gap-1 px-4 py-2 bg-red-600 text-white rounded-md text-sm font-semibold hover:bg-red-700 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Reject
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-portal-layout>
