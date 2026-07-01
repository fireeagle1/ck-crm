<x-admin-layout>
    <x-slot:title>Knowledgebase</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Knowledgebase Articles</h1>
        <a href="{{ route('admin.articles.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
            New Article
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Title</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Category</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Customer</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Public</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Created</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($articles as $article)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ $article->title }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $article->category ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $article->customer?->company_name ?? 'All' }}</td>
                        <td class="px-4 py-3">
                            @if ($article->is_public)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Yes</span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700">No</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $article->created_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 flex gap-3">
                            <a href="{{ route('admin.articles.edit', $article) }}" class="text-blue-600 hover:underline text-sm">Edit</a>
                            <form method="POST" action="{{ route('admin.articles.destroy', $article) }}" class="inline"
                                  onsubmit="return confirm('Delete this article?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No articles.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $articles->links() }}</div>
</x-admin-layout>
