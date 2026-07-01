@php
    $adminNavMain = [
        ['route' => 'admin.dashboard', 'label' => 'Dashboard'],
        ['route' => 'admin.customers.index', 'label' => 'Customers'],
        ['route' => 'admin.tickets.index', 'label' => 'Tickets'],
        ['route' => 'admin.services.index', 'label' => 'Services'],
        ['route' => 'admin.invoices.index', 'label' => 'Invoices'],
    ];
    $adminNavMore = [
        ['route' => 'admin.domains.index', 'label' => 'Domains'],
        ['route' => 'admin.assets.index', 'label' => 'CMDB'],
        ['route' => 'admin.articles.index', 'label' => 'Knowledgebase'],
        ['route' => 'admin.users.index', 'label' => 'Users'],
        ['route' => 'admin.communications.index', 'label' => 'Communications'],
        ['route' => 'admin.settings.index', 'label' => 'Settings'],
    ];
@endphp

<header class="bg-slate-950 sticky top-0 z-40">
    {{-- Environment banner --}}
    @if (app()->environment('local', 'staging'))
        <div class="bg-red-600 text-white text-center text-xs font-bold py-1 px-4 tracking-wide uppercase">
            {{ app()->environment() }} &mdash; {{ request()->getHost() }}
        </div>
    @endif

    {{-- Top bar with brand + search + account --}}
    <div class="border-b border-white/10">
        <div class="max-w-7xl mx-auto px-4 h-14 flex items-center justify-between">
            {{-- Brand --}}
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                @php $logoPath = \App\Models\Setting::get('logo_path'); @endphp
                @if ($logoPath)
                    <img src="{{ asset($logoPath) }}" alt="{{ \App\Models\Setting::get('site_name', 'CK Admin') }}" class="h-10 w-auto">
                @else
                    <span class="text-xl font-bold text-white tracking-tight">CK Enterprises</span>
                @endif
            </a>

            {{-- Right: Search + Account --}}
            <div class="flex items-center gap-4">
                <form action="{{ route('admin.search') }}" method="GET" class="hidden lg:block">
                    <input type="text" name="q" placeholder="Search..." value="{{ request('q') }}"
                           class="w-52 rounded bg-white/10 border-0 text-sm text-white placeholder-gray-400 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:bg-white/20">
                </form>

                <a href="{{ route('portal.dashboard') }}" class="text-sm text-gray-400 hover:text-white transition">&larr; Portal</a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-400 hover:text-white transition">Sign out</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Navigation bar --}}
    <div class="max-w-7xl mx-auto px-4 relative z-50">
        <nav class="flex items-center gap-1 h-11 -mb-px overflow-x-auto">
            @foreach ($adminNavMain as $item)
                <a href="{{ route($item['route']) }}"
                   class="px-3 py-2 text-sm font-semibold whitespace-nowrap border-b-2 transition
                          {{ request()->routeIs($item['route'] . '*') ? 'border-blue-400 text-white' : 'border-transparent text-gray-300 hover:text-white hover:border-gray-500' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach

            {{-- More dropdown --}}
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" @click.away="open = false"
                        class="px-3 py-2 text-sm font-semibold whitespace-nowrap border-b-2 transition flex items-center gap-1
                               {{ collect($adminNavMore)->contains(fn($i) => request()->routeIs($i['route'] . '*')) ? 'border-blue-400 text-white' : 'border-transparent text-gray-300 hover:text-white hover:border-gray-500' }}">
                    More
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div x-show="open" x-transition
                     class="absolute left-0 mt-1 w-48 bg-white rounded-lg shadow-lg border py-1 z-50">
                    @foreach ($adminNavMore as $item)
                        <a href="{{ route($item['route']) }}"
                           class="block px-4 py-2 text-sm {{ request()->routeIs($item['route'] . '*') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </nav>
    </div>
</header>
