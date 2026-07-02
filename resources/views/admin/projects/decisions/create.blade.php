<x-admin-layout>
    <x-slot:title>Record Decision</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Record Decision</h1>
        <a href="{{ route('admin.projects.show', $project) }}" class="text-sm text-blue-600 hover:underline">&larr; Back to Project</a>
    </div>

    <div class="bg-white rounded-lg border p-6 max-w-3xl">
        <form method="POST" action="{{ route('admin.projects.decisions.store', $project) }}">
            @csrf

            <div class="space-y-5">
                {{-- Title --}}
                <div>
                    <label for="title" class="block text-sm font-semibold text-gray-700">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" id="title" value="{{ old('title') }}" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Decision title">
                    @error('title') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Description --}}
                <div>
                    <label for="description" class="block text-sm font-semibold text-gray-700">Description</label>
                    <textarea name="description" id="description" rows="4"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Optional details about this decision">{{ old('description') }}</textarea>
                    @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Category --}}
                <div>
                    <label for="category" class="block text-sm font-semibold text-gray-700">Category</label>
                    <select name="category" id="category"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select category...</option>
                        @foreach (\App\Models\ProjectDecision::CATEGORIES as $category)
                            <option value="{{ $category }}" {{ old('category') === $category ? 'selected' : '' }}>
                                {{ $category }}
                            </option>
                        @endforeach
                    </select>
                    @error('category') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700">
                        Record Decision
                    </button>
                    <a href="{{ route('admin.projects.show', $project) }}" class="px-5 py-2.5 border rounded-md text-sm font-semibold hover:bg-gray-50">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
