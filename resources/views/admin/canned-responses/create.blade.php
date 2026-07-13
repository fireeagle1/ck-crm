<x-admin-layout>
    <x-slot:title>New Canned Response</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">New Canned Response</h1>
        <a href="{{ route('admin.canned-responses.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Back</a>
    </div>

    <div class="max-w-2xl">
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <form method="POST" action="{{ route('admin.canned-responses.store') }}">
                @csrf

                <div class="space-y-5">
                    <div>
                        <label for="title" class="block text-sm font-semibold text-gray-700">Title <span class="text-red-500">*</span></label>
                        <input type="text" name="title" id="title" required value="{{ old('title') }}"
                               placeholder="Short name shown in the dropdown, e.g. 'DNS Propagation'"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                        @error('title') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="category" class="block text-sm font-semibold text-gray-700">Category</label>
                        <input type="text" name="category" id="category" value="{{ old('category') }}"
                               placeholder="Optional grouping, e.g. 'Email', 'DNS', 'General'"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <p class="text-xs text-gray-400 mt-1">Used to organise responses. Leave blank if not needed.</p>
                    </div>

                    <div>
                        <label for="body" class="block text-sm font-semibold text-gray-700">Response Body <span class="text-red-500">*</span></label>
                        <textarea name="body" id="body" rows="8" required
                                  placeholder="The full reply text that will be inserted into the ticket reply..."
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">{{ old('body') }}</textarea>
                        @error('body') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="sort_order" class="block text-sm font-semibold text-gray-700">Sort Order</label>
                        <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', 0) }}" min="0"
                               class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <p class="text-xs text-gray-400 mt-1">Lower numbers appear first in the dropdown.</p>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition">
                            Create Response
                        </button>
                        <a href="{{ route('admin.canned-responses.index') }}" class="px-5 py-2.5 border rounded-md text-sm font-semibold hover:bg-gray-50 transition">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
