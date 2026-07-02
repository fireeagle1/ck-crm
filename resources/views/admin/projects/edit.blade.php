<x-admin-layout>
    <x-slot:title>Edit {{ $project->title }}</x-slot:title>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-md text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-md text-sm">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Edit Project</h1>
        <a href="{{ route('admin.projects.show', $project) }}" class="text-sm text-blue-600 hover:underline">&larr; Back</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.projects.update', $project) }}">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" id="title" required
                           value="{{ old('title', $project->title) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('title') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" id="description" rows="4"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('description', $project->description) }}</textarea>
                    @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="status"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            @if($project->status === 'Completed') disabled @endif>
                        @foreach (\App\Models\Project::STATUSES as $status)
                            <option value="{{ $status }}" @selected(old('status', $project->status) === $status)>
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                    @if($project->status === 'Completed')
                        <p class="text-sm text-gray-500 mt-1">Status changes are locked on completed projects. Use the reopen button below.</p>
                    @endif
                    @error('status') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                        Save Changes
                    </button>
                    <a href="{{ route('admin.projects.show', $project) }}" class="px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- Reopen button for Completed projects --}}
    @if($project->status === 'Completed')
        <div class="mt-6 bg-white rounded-lg shadow-sm border p-6 max-w-2xl">
            <h2 class="text-lg font-semibold text-gray-800 mb-2">Reopen Project</h2>
            <p class="text-sm text-gray-600 mb-4">This project is completed. Reopening it will set the status back to "In Progress".</p>
            <form method="POST" action="{{ route('admin.projects.reopen', $project) }}">
                @csrf
                <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-md text-sm font-medium hover:bg-yellow-600">
                    Reopen Project
                </button>
            </form>
        </div>
    @endif
</x-admin-layout>
