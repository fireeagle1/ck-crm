<x-admin-layout>
    <x-slot:title>Edit Article</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Edit Article</h1>
        <a href="{{ route('admin.articles.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Articles</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-3xl">
        <form method="POST" action="{{ route('admin.articles.update', $article) }}">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" id="title" required
                           value="{{ old('title', $article->title) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('title') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                        <input type="text" name="category" id="category"
                               value="{{ old('category', $article->category) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="company_id" class="block text-sm font-medium text-gray-700">Customer</label>
                        <select name="company_id" id="company_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All customers</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->company_id }}" {{ old('company_id', $article->company_id) == $customer->company_id ? 'selected' : '' }}>
                                    {{ $customer->company_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Content <span class="text-red-500">*</span></label>
                    <div class="border border-gray-300 rounded-md shadow-sm overflow-hidden focus-within:ring-1 focus-within:ring-blue-500 focus-within:border-blue-500">
                        <div class="flex items-center gap-1 px-2 py-1.5 bg-gray-50 border-b border-gray-300">
                            <button type="button" onclick="formatDoc('bold')" title="Bold" class="px-2 py-1 text-sm font-bold rounded hover:bg-gray-200">B</button>
                            <button type="button" onclick="formatDoc('italic')" title="Italic" class="px-2 py-1 text-sm italic rounded hover:bg-gray-200">I</button>
                            <button type="button" onclick="formatDoc('createLink')" title="Link" class="px-2 py-1 text-sm rounded hover:bg-gray-200">🔗</button>
                            <span class="w-px h-5 bg-gray-300 mx-1"></span>
                            <button type="button" onclick="formatDoc('formatBlock', 'h2')" title="Heading" class="px-2 py-1 text-sm font-semibold rounded hover:bg-gray-200">H</button>
                            <button type="button" onclick="formatDoc('formatBlock', 'p')" title="Paragraph" class="px-2 py-1 text-sm rounded hover:bg-gray-200">¶</button>
                        </div>
                        <div id="editor" contenteditable="true"
                             class="min-h-[250px] p-3 prose prose-sm max-w-none focus:outline-none"
                             oninput="document.getElementById('content').value = this.innerHTML">
                            {!! old('content', $article->content) !!}
                        </div>
                    </div>
                    <input type="hidden" name="content" id="content" value="{{ old('content', $article->content) }}">
                    @error('content') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <script>
                    function formatDoc(command, value = null) {
                        if (command === 'createLink') {
                            value = prompt('Enter URL:', 'https://');
                            if (!value) return;
                        }
                        document.execCommand(command, false, value);
                        document.getElementById('content').value = document.getElementById('editor').innerHTML;
                    }
                </script>

                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_public" value="0">
                    <input type="checkbox" name="is_public" id="is_public" value="1"
                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                           {{ old('is_public', $article->is_public) ? 'checked' : '' }}>
                    <label for="is_public" class="text-sm text-gray-700">Visible to all customers</label>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                        Save Changes
                    </button>
                    <a href="{{ route('admin.articles.index') }}" class="px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
