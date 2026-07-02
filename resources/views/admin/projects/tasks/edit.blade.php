<x-admin-layout>
    <x-slot:title>Edit Task — {{ $task->title }}</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Edit Task</h1>
        <a href="{{ route('admin.projects.show', $project) }}" class="text-sm text-blue-600 hover:underline">&larr; Back to Project</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.projects.tasks.update', [$project, $task]) }}">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" id="title" required
                           value="{{ old('title', $task->title) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('title') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" id="description" rows="4"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('description', $task->description) }}</textarea>
                    @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="status"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @foreach (\App\Models\ProjectTask::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('status', $task->status) === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        @error('status') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="display_order" class="block text-sm font-medium text-gray-700">Display Order</label>
                        <input type="number" name="display_order" id="display_order" min="0"
                               value="{{ old('display_order', $task->display_order) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('display_order') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
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
</x-admin-layout>
