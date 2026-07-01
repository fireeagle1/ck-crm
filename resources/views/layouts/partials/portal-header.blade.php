@php
    $nav = [
        ['route' => 'portal.dashboard', 'label' => 'Dashboard'],
        ['route' => 'portal.services.index', 'label' => 'My Websites'],
        ['route' => 'portal.tickets.index', 'label' => 'Support Tickets'],
        ['route' => 'portal.knowledgebase.index', 'label' => 'Knowledgebase'],
        ['route' => 'portal.domains.index', 'label' => 'Domains'],
        ['route' => 'portal.account.show', 'label' => 'Account'],
    ];
@endphp

<header class="bg-slate-900 text-white sticky top-0 z-40 shadow">
    {{-- Impersonation banner --}}
    @if (session('impersonating_from'))
        <div class="bg-red-600 text-white text-center text-sm py-2 px-4">
            Impersonating: {{ auth()->user()->full_name }} ({{ auth()->user()->email }}).
            <form action="{{ route('admin.impersonate.stop') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="underline font-semibold ml-2">Return to admin</button>
            </form>
        </div>
    @endif

    <nav class="max-w-7xl mx-auto px-4">
        <div class="h-16 flex items-center justify-between">
            {{-- Brand --}}
            <a href="{{ route('portal.dashboard') }}" class="flex items-center gap-2">
                @php $logoPath = \App\Models\Setting::get('logo_path'); @endphp
                @if ($logoPath)
                    <img src="{{ asset('storage/' . $logoPath) }}" alt="{{ \App\Models\Setting::get('site_name', 'CK Enterprises') }}" class="h-8 w-auto">
                @else
                    <span class="text-lg font-semibold tracking-wide">CK Enterprises</span>
                @endif
            </a>

            {{-- Desktop nav --}}
            <div class="hidden md:flex items-center gap-1">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}"
                       class="px-3 py-2 rounded text-sm font-medium transition
                              {{ request()->routeIs($item['route'] . '*') ? 'bg-white text-slate-900' : 'text-gray-200 hover:bg-white/10' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach

                <span class="w-px h-5 bg-white/20 mx-2"></span>

                <form action="{{ route('portal.billing.portal') }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3 py-2 rounded text-sm font-medium text-gray-200 hover:bg-white/10 transition">
                        Billing
                    </button>
                </form>
            </div>

            {{-- User dropdown --}}
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-white/10">
                    <img src="https://www.gravatar.com/avatar/{{ md5(strtolower(trim(auth()->user()->email))) }}?s=32&d=identicon"
                         alt="" class="h-8 w-8 rounded-full">
                    <span class="hidden sm:block text-sm">{{ auth()->user()->full_name ?: 'Account' }}</span>
                </button>

                <div x-show="open" @click.away="open = false" x-transition
                     class="absolute right-0 mt-2 w-56 bg-white text-gray-900 rounded-lg shadow-lg border py-2 z-50">
                    <div class="px-4 py-2 border-b">
                        <p class="font-medium text-sm truncate">{{ auth()->user()->full_name }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
                    </div>

                    <a href="{{ route('portal.account.show') }}" class="block px-4 py-2 text-sm hover:bg-gray-50">Account</a>

                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm hover:bg-gray-50">Admin Panel</a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" class="border-t mt-1 pt-1">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50">Sign out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
</header>
