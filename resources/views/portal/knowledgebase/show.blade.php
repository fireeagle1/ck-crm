<x-portal-layout>
    <x-slot:title>{{ $article->title }}</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('portal.knowledgebase.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Knowledgebase</a>
    </div>

    <article class="bg-white rounded-lg shadow-sm border p-6 max-w-3xl">
        <header class="mb-4 pb-4 border-b">
            <h1 class="text-2xl font-semibold">{{ $article->title }}</h1>
            <div class="flex gap-4 mt-2 text-sm text-gray-500">
                @if ($article->category)
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                        {{ $article->category }}
                    </span>
                @endif
                <span>Published {{ $article->created_at->format('M j, Y') }}</span>
            </div>
        </header>

        <div class="prose prose-sm max-w-none text-gray-700">
            {!! nl2br(e($article->content)) !!}
        </div>
    </article>
</x-portal-layout>
