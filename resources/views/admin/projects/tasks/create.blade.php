<x-admin-layout>
    <x-slot:title>Add Task — {{ $project->title }}</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Add Task</h1>
        <a href="{{ route('admin.projects.show', $project) }}" class="text-sm text-blue-600 hover:underline">&larr; Back to Project</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.projects.tasks.store', $project) }}">
            @csrf

            <div class="space-y-4">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" id="title" required value="{{ old('title') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('title') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" id="description" rows="4"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('description') }}</textarea>
                    @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                        Create Task
                    </button>
                    <a href="{{ route('admin.projects.show', $project) }}" class="px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
