@php
    $adminNavMain = [
        ['route' => 'admin.dashboard', 'label' => 'Dashboard'],
        ['route' => 'admin.customers.index', 'label' => 'Customers'],
        ['route' => 'admin.tickets.index', 'label' => 'Tickets'],
        ['route' => 'admin.services.index', 'label' => 'Services'],
        ['route' => 'admin.invoices.index', 'label' => 'Invoices'],
        ['route' => 'admin.domains.index', 'label' => 'Domains'],
    ];
    $adminNavMore = [
        ['route' => 'admin.assets.index', 'label' => 'CMDB / Assets'],
        ['route' => 'admin.articles.index', 'label' => 'Knowledgebase'],
        ['route' => 'admin.users.index', 'label' => 'Users'],
        ['route' => 'admin.communications.index', 'label' => 'Communications'],
    ];
    $adminNavTools = [
        ['route' => 'admin.services.cpanel-mapping', 'label' => 'cPanel Mapping'],
        ['route' => 'admin.services.stripe-mapping', 'label' => 'Stripe Mapping'],
        ['route' => 'admin.cleanup.index', 'label' => 'Data Cleanup'],
        ['route' => 'admin.cleanup.review', 'label' => 'Service Review'],
        ['route' => 'admin.settings.import', 'label' => 'Import Data'],
        ['route' => 'admin.settings.tasks', 'label' => 'Scheduled Tasks'],
        ['route' => 'admin.settings.index', 'label' => 'Settings'],
    ];
@endphp

<header class="bg-slate-950 sticky top-0" style="z-index: 9999;">
    {{-- Environment banner --}}
    @if (app()->environment('local', 'staging'))
        <div class="bg-red-600 text-white text-center text-xs font-bold py-1 px-4 tracking-wide uppercase">
            {{ app()->environment() }} &mdash; {{ request()->getHost() }}
        </div>
    @endif

    {{-- Top bar --}}
    <div class="border-b border-white/10">
        <div class="max-w-7xl mx-auto px-4 h-14 flex items-center justify-between">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                @php $logoPath = \App\Models\Setting::get('logo_path'); @endphp
                @if ($logoPath)
                    <img src="{{ asset($logoPath) }}" alt="{{ \App\Models\Setting::get('site_name', 'CK Admin') }}" class="h-10 w-auto">
                @else
                    <span class="text-xl font-bold text-white tracking-tight">CK Enterprises</span>
                @endif
            </a>

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
    <div class="max-w-7xl mx-auto px-4">
        <nav class="flex items-center gap-1 h-11">
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
                <div x-show="open" x-transition class="absolute left-0 mt-1 w-48 bg-white rounded-lg shadow-xl border py-1" style="z-index: 99999;">
                    @foreach ($adminNavMore as $item)
                        <a href="{{ route($item['route']) }}"
                           class="block px-4 py-2 text-sm {{ request()->routeIs($item['route'] . '*') ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Tools dropdown --}}
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" @click.away="open = false"
                        class="px-3 py-2 text-sm font-semibold whitespace-nowrap border-b-2 transition flex items-center gap-1
                               {{ collect($adminNavTools)->contains(fn($i) => request()->routeIs($i['route'] . '*')) ? 'border-blue-400 text-white' : 'border-transparent text-gray-300 hover:text-white hover:border-gray-500' }}">
                    Tools
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-transition class="absolute left-0 mt-1 w-48 bg-white rounded-lg shadow-xl border py-1" style="z-index: 99999;">
                    @foreach ($adminNavTools as $item)
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
