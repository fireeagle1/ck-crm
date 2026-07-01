@php
    $adminNav = [
        ['route' => 'admin.dashboard', 'label' => 'Dashboard'],
        ['route' => 'admin.customers.index', 'label' => 'Customers'],
        ['route' => 'admin.tickets.index', 'label' => 'Tickets'],
        ['route' => 'admin.services.index', 'label' => 'Services'],
        ['route' => 'admin.assets.index', 'label' => 'CMDB'],
        ['route' => 'admin.articles.index', 'label' => 'Knowledgebase'],
        ['route' => 'admin.users.index', 'label' => 'Users'],
    ];
@endphp

<header class="bg-gray-900 text-white sticky top-0 z-40 shadow">
    {{-- Environment banner --}}
    @if (app()->environment('local', 'staging'))
        <div class="bg-red-600/80 text-white text-center text-xs py-1 px-4">
            {{ strtoupper(app()->environment()) }} &mdash; {{ request()->getHost() }}
        </div>
    @endif

    <nav class="max-w-7xl mx-auto px-4">
        <div class="h-16 flex items-center justify-between">
            {{-- Brand --}}
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                <span class="text-lg font-semibold tracking-wide">CK Admin</span>
            </a>

            {{-- Desktop nav --}}
            <div class="hidden md:flex items-center gap-1">
                @foreach ($adminNav as $item)
                    <a href="{{ route($item['route']) }}"
                       class="px-3 py-2 rounded text-sm font-medium transition
                              {{ request()->routeIs($item['route'] . '*') ? 'bg-white/15' : 'text-gray-200 hover:bg-white/10' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>

            {{-- Right side --}}
            <div class="flex items-center gap-3">
                <a href="{{ route('portal.dashboard') }}" class="text-xs text-gray-300 hover:text-white">
                    &larr; Customer portal
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-300 hover:text-white">Sign out</button>
                </form>
            </div>
        </div>
    </nav>
</header>
