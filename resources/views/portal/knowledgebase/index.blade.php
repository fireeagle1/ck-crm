<x-portal-layout>
    <x-slot:title>Knowledgebase</x-slot:title>

    <h1 class="text-2xl font-semibold mb-4">Knowledgebase</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($articles as $article)
            <a href="{{ route('portal.knowledgebase.show', $article) }}"
               class="bg-white rounded-lg shadow-sm border p-5 hover:shadow-md transition">
                <h3 class="font-semibold text-gray-900">{{ $article->title }}</h3>
                <p class="text-sm text-gray-500 mt-1">{{ $article->category ?? 'General' }}</p>
                <p class="text-xs text-gray-400 mt-2">{{ $article->created_at->format('M j, Y') }}</p>
            </a>
        @empty
            <p class="text-gray-500 col-span-full">No articles available.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $articles->links() }}</div>
</x-portal-layout>
