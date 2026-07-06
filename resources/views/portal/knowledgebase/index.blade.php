<x-portal-layout>
    <x-slot:title>Help Centre</x-slot:title>

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold">Help Centre</h1>
        <p class="text-sm text-gray-500 mt-1">Browse articles or search for answers. Can't find what you need? Raise a ticket.</p>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('portal.knowledgebase.index') }}" class="mb-5">
        <div class="flex gap-2 max-w-lg">
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search articles..."
                   class="flex-1 rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                Search
            </button>
            @if (request('search') || request('category'))
                <a href="{{ route('portal.knowledgebase.index') }}" class="px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">
                    Clear
                </a>
            @endif
        </div>
    </form>

    {{-- Category Tabs --}}
    @if ($categories->isNotEmpty())
        <div class="flex flex-wrap gap-2 mb-5">
            <a href="{{ route('portal.knowledgebase.index', ['search' => request('search')]) }}"
               class="px-3 py-1.5 rounded-full text-sm font-medium {{ !request('category') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                All
            </a>
            @foreach ($categories as $cat)
                <a href="{{ route('portal.knowledgebase.index', ['category' => $cat, 'search' => request('search')]) }}"
                   class="px-3 py-1.5 rounded-full text-sm font-medium {{ request('category') === $cat ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    {{ $cat }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- Articles Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($articles as $article)
            <a href="{{ route('portal.knowledgebase.show', $article) }}"
               class="bg-white rounded-lg shadow-sm border p-5 hover:shadow-md transition group">
                @if ($article->category)
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 mb-2">
                        {{ $article->category }}
                    </span>
                @endif
                <h3 class="font-semibold text-gray-900 group-hover:text-blue-600">{{ $article->title }}</h3>
                <p class="text-sm text-gray-500 mt-2 line-clamp-2">{{ Str::limit(strip_tags($article->content), 120) }}</p>
                <p class="text-xs text-gray-400 mt-3">
                    @if ($article->updated_at->gt($article->created_at->addMinute()))
                        Updated {{ $article->updated_at->diffForHumans() }}
                    @else
                        {{ $article->created_at->format('M j, Y') }}
                    @endif
                </p>
            </a>
        @empty
            <div class="col-span-full text-center py-8">
                <p class="text-gray-500">No articles found{{ request('search') ? ' for "' . request('search') . '"' : '' }}.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $articles->links() }}</div>

    {{-- Can't find answer? Raise a ticket --}}
    <div class="mt-8 bg-gray-50 border border-gray-200 rounded-lg p-6 text-center max-w-xl mx-auto">
        <h2 class="text-lg font-semibold text-gray-900">Can't find what you're looking for?</h2>
        <p class="text-sm text-gray-600 mt-1">Our support team is happy to help. Raise a ticket and we'll get back to you.</p>
        <a href="{{ route('portal.tickets.create') }}"
           class="inline-flex items-center mt-4 px-5 py-2.5 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
            Raise a Support Ticket
        </a>
    </div>
</x-portal-layout>
