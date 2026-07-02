<x-admin-layout>
    <x-slot:title>{{ $project->title }}</x-slot:title>

    {{-- Flash messages for warning --}}
    @if(session('warning'))
        <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 text-yellow-700 rounded-md text-sm">
            {{ session('warning') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $project->title }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $project->customer->company_name ?? '—' }}</p>
        </div>
        <div class="flex items-center gap-3">
            @php
                $statusColors = [
                    'Not Started' => 'bg-gray-100 text-gray-700',
                    'In Progress' => 'bg-blue-100 text-blue-700',
                    'On Hold' => 'bg-yellow-100 text-yellow-700',
                    'Awaiting Approval' => 'bg-purple-100 text-purple-700',
                    'Completed' => 'bg-green-100 text-green-700',
                ];
            @endphp
            <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full {{ $statusColors[$project->status] ?? 'bg-gray-100 text-gray-700' }}">
                {{ $project->status }}
            </span>
            <a href="{{ route('admin.projects.edit', $project) }}" class="px-3 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">Edit</a>
            <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('Are you sure you want to delete this project?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-3 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700">Delete</button>
            </form>
        </div>
    </div>

    {{-- Progress bar --}}
    <div class="mb-6">
        <div class="flex items-center gap-3">
            <div class="flex-1 bg-gray-200 rounded-full h-3">
                <div class="bg-blue-600 h-3 rounded-full transition-all" style="width: {{ $project->progress_percentage }}%"></div>
            </div>
            <span class="text-sm font-medium text-gray-700">{{ $project->progress_percentage }}%</span>
        </div>
    </div>

    {{-- Description --}}
    @if($project->description)
        <div class="bg-white rounded-lg shadow-sm border p-4 mb-6">
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $project->description }}</p>
        </div>
    @endif

    {{-- Tasks Section --}}
    <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Tasks</h2>
            <a href="{{ route('admin.projects.tasks.create', $project) }}" class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">+ Add Task</a>
        </div>

        @if($project->tasks->isEmpty())
            <p class="text-sm text-gray-500">No tasks yet.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach($project->tasks as $task)
                    <li class="flex items-center justify-between py-3">
                        <div class="flex items-center gap-3">
                            @php
                                $taskStatusColors = [
                                    'To Do' => 'bg-gray-100 text-gray-700',
                                    'In Progress' => 'bg-blue-100 text-blue-700',
                                    'Done' => 'bg-green-100 text-green-700',
                                ];
                            @endphp
                            <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $taskStatusColors[$task->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $task->status }}
                            </span>
                            <span class="text-sm text-gray-900">{{ $task->title }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.projects.tasks.edit', [$project, $task]) }}" class="text-blue-600 hover:underline text-xs">Edit</a>
                            <form method="POST" action="{{ route('admin.projects.tasks.destroy', [$project, $task]) }}" onsubmit="return confirm('Delete this task?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-xs">Delete</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Documents Section --}}
    <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Documents</h2>

        @if($project->documents->isEmpty())
            <p class="text-sm text-gray-500 mb-4">No documents uploaded yet.</p>
        @else
            <div class="overflow-x-auto mb-4">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Label</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Type</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Filename</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Size</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Uploaded</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Uploader</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Approval</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($project->documents as $document)
                            <tr>
                                <td class="px-3 py-2 text-gray-900">{{ $document->label }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $document->document_type }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $document->original_filename }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ number_format($document->file_size / 1024, 1) }} KB</td>
                                <td class="px-3 py-2 text-gray-500">{{ $document->created_at->format('d M Y') }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $document->uploader->name ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    @if($document->approvalRequest)
                                        @php
                                            $approvalColors = [
                                                'Pending' => 'bg-yellow-100 text-yellow-700',
                                                'Approved' => 'bg-green-100 text-green-700',
                                                'Rejected' => 'bg-red-100 text-red-700',
                                            ];
                                        @endphp
                                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $approvalColors[$document->approvalRequest->status] ?? 'bg-gray-100 text-gray-700' }}">
                                            {{ $document->approvalRequest->status }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        @if(!$document->approvalRequest || $document->approvalRequest->status !== 'Pending')
                                            <form method="POST" action="{{ route('admin.projects.approvals.document', [$project, $document]) }}">
                                                @csrf
                                                <button type="submit" class="text-purple-600 hover:underline text-xs">Request Approval</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.projects.documents.destroy', [$project, $document]) }}" onsubmit="return confirm('Delete this document?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline text-xs">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Upload form --}}
        <div class="border-t pt-4">
            <h3 class="text-sm font-medium text-gray-700 mb-3">Upload Document</h3>
            <form method="POST" action="{{ route('admin.projects.documents.store', $project) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label for="label" class="block text-xs text-gray-600 mb-1">Label</label>
                    <input type="text" name="label" id="label" required
                           class="block w-48 rounded-md border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Document label">
                    @error('label') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="document_type" class="block text-xs text-gray-600 mb-1">Type</label>
                    <select name="document_type" id="document_type" required
                            class="block w-40 rounded-md border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                        @foreach(\App\Models\ProjectDocument::TYPES as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('document_type') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="file" class="block text-xs text-gray-600 mb-1">File</label>
                    <input type="file" name="file" id="file" required
                           class="block text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    @error('file') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">Upload</button>
            </form>
        </div>
    </div>

    {{-- Comments Section --}}
    <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Comments</h2>

        @if($project->comments->isEmpty())
            <p class="text-sm text-gray-500 mb-4">No comments yet.</p>
        @else
            <div class="space-y-3 mb-4">
                @foreach($project->comments as $comment)
                    <div class="p-3 rounded-md border {{ $comment->is_internal ? 'border-l-4 border-l-amber-400 bg-amber-50' : 'bg-gray-50' }}">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-gray-800">
                                {{ $comment->user->name ?? 'Unknown' }}
                                @if($comment->is_internal)
                                    <span class="ml-2 inline-flex px-1.5 py-0.5 text-xs font-medium rounded bg-amber-200 text-amber-800">Internal</span>
                                @endif
                            </span>
                            <span class="text-xs text-gray-500">{{ $comment->created_at->format('d M Y H:i') }}</span>
                        </div>
                        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $comment->body }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Add comment form --}}
        <div class="border-t pt-4">
            <h3 class="text-sm font-medium text-gray-700 mb-3">Add Comment</h3>
            <form method="POST" action="{{ route('admin.projects.comments.store', $project) }}">
                @csrf
                <div class="mb-3">
                    <textarea name="body" rows="3" required placeholder="Write a comment..."
                              class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">{{ old('body') }}</textarea>
                    @error('body') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_internal" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Internal (not visible to customer)
                    </label>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">Post Comment</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Decisions Section --}}
    <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Decisions</h2>
            <a href="{{ route('admin.projects.decisions.create', $project) }}" class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">+ Add Decision</a>
        </div>

        @if($project->decisions->isEmpty())
            <p class="text-sm text-gray-500">No decisions recorded yet.</p>
        @else
            <div class="space-y-3">
                @foreach($project->decisions as $decision)
                    <div class="p-3 rounded-md border bg-gray-50">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-900">{{ $decision->title }}</span>
                                @if($decision->category)
                                    @php
                                        $categoryColors = [
                                            'Design Requirement' => 'bg-pink-100 text-pink-700',
                                            'Client Decision' => 'bg-indigo-100 text-indigo-700',
                                            'Technical Decision' => 'bg-teal-100 text-teal-700',
                                        ];
                                    @endphp
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $categoryColors[$decision->category] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ $decision->category }}
                                    </span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-500">{{ $decision->date_recorded->format('d M Y') }}</span>
                                <a href="{{ route('admin.projects.decisions.edit', [$project, $decision]) }}" class="text-blue-600 hover:underline text-xs">Edit</a>
                                <form method="POST" action="{{ route('admin.projects.decisions.destroy', [$project, $decision]) }}" onsubmit="return confirm('Delete this decision?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline text-xs">Delete</button>
                                </form>
                            </div>
                        </div>
                        @if($decision->description)
                            <p class="text-sm text-gray-600 mt-1">{{ $decision->description }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Approval Requests Section --}}
    <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Approval Requests</h2>
            <form method="POST" action="{{ route('admin.projects.approvals.completion', $project) }}">
                @csrf
                <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-purple-600 text-white rounded-md text-sm font-medium hover:bg-purple-700">Request Completion Approval</button>
            </form>
        </div>

        @if($project->approvalRequests->isEmpty())
            <p class="text-sm text-gray-500">No approval requests yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Type</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Status</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Document</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Responded By</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Responded At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($project->approvalRequests as $approval)
                            <tr>
                                <td class="px-3 py-2 text-gray-900">{{ $approval->type }}</td>
                                <td class="px-3 py-2">
                                    @php
                                        $approvalStatusColors = [
                                            'Pending' => 'bg-yellow-100 text-yellow-700',
                                            'Approved' => 'bg-green-100 text-green-700',
                                            'Rejected' => 'bg-red-100 text-red-700',
                                        ];
                                    @endphp
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $approvalStatusColors[$approval->status] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ $approval->status }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-gray-500">{{ $approval->document->label ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $approval->respondedBy->name ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $approval->responded_at ? $approval->responded_at->format('d M Y H:i') : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Status History --}}
    <div class="bg-white rounded-lg shadow-sm border p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Status History</h2>

        @if($project->statusLogs->isEmpty())
            <p class="text-sm text-gray-500">No status changes recorded.</p>
        @else
            <div class="space-y-2">
                @foreach($project->statusLogs as $log)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $statusColors[$log->status] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ $log->status }}
                        </span>
                        <span class="text-gray-600">by {{ $log->changedBy->name ?? 'Unknown' }}</span>
                        <span class="text-gray-400">{{ $log->created_at->format('d M Y H:i') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-admin-layout>
