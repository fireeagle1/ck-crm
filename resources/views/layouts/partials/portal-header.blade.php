@php
    // Always-visible navigation items
    $nav = [
        ['route' => 'portal.dashboard', 'label' => 'Dashboard'],
    ];

    // Conditionally show Services link based on customer having services or hosting products
    if (!empty($showServices)) {
        $nav[] = ['route' => 'portal.services.index', 'label' => 'Services'];
    }

    // Always show Support
    $nav[] = ['route' => 'portal.tickets.index', 'label' => 'Support'];

    // Conditionally show Invoices link based on customer having invoices
    if (!empty($showInvoices)) {
        $nav[] = ['route' => 'portal.invoices.index', 'label' => 'Invoices'];
    }

    // Conditionally show Domains link based on customer having domains
    if (!empty($showDomains)) {
        $nav[] = ['route' => 'portal.domains.index', 'label' => 'Domains'];
    }

    // Always show Shop (if products are visible to customer)
    if (!isset($showShop) || $showShop) {
        $nav[] = ['route' => 'portal.shop.index', 'label' => 'Shop'];
    }

    // Conditionally show Orders link based on customer having orders
    if (!empty($showOrders)) {
        $nav[] = ['route' => 'portal.orders.index', 'label' => 'Orders'];
    }

    // Conditionally show Projects if customer has active projects
    $hasActiveProjects = \App\Models\Project::where('company_id', auth()->user()->company_id)
        ->where('status', '!=', 'Completed')
        ->exists();

    if ($hasActiveProjects) {
        $nav[] = ['route' => 'portal.projects.index', 'label' => 'Projects'];
    }

    // Always show Help
    $nav[] = ['route' => 'portal.knowledgebase.index', 'label' => 'Help'];
@endphp

<header class="bg-slate-950 sticky top-0 z-40">
    {{-- Impersonation banner --}}
    @if (session('impersonating_from'))
        <div class="bg-red-600 text-white text-center text-sm font-semibold py-2 px-4">
            Impersonating: {{ auth()->user()->full_name }} ({{ auth()->user()->email }})
            <form action="{{ route('admin.impersonate.stop') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="underline ml-2">Return to admin</button>
            </form>
        </div>
    @endif

    <div class="max-w-7xl mx-auto px-4">
        <div class="h-16 flex items-center justify-between">
            {{-- Brand --}}
            <a href="{{ route('portal.dashboard') }}" class="flex items-center gap-3">
                @php $logoPath = \App\Models\Setting::get('logo_path'); @endphp
                @if ($logoPath)
                    <img src="{{ asset($logoPath) }}" alt="{{ \App\Models\Setting::get('site_name', 'CK Enterprises') }}" class="h-10 w-auto">
                @else
                    <span class="text-xl font-bold text-white tracking-tight">CK Enterprises</span>
                @endif
            </a>

            {{-- Desktop nav --}}
            <div class="hidden md:flex items-center gap-1">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}"
                       class="px-3 py-2 text-sm font-semibold transition rounded
                              {{ request()->routeIs($item['route'] . '*') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach

                {{-- Billing button --}}
                <form action="{{ route('portal.billing.portal') }}" method="POST" class="ml-1">
                    @csrf
                    <button type="submit" class="px-3 py-2 text-sm font-semibold text-gray-300 hover:bg-white/10 hover:text-white rounded transition inline-flex items-center gap-1">
                        Billing
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </button>
                </form>
            </div>

            {{-- Cart icon --}}
            <a href="{{ route('portal.cart.index') }}" class="relative p-2 text-gray-300 hover:text-white transition" title="View Cart">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                </svg>
                @if ($cartItemCount > 0)
                    <span class="absolute top-0 right-0 inline-flex items-center justify-center h-4 min-w-[1rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none ring-2 ring-slate-950">
                        {{ $cartItemCount }}
                    </span>
                @endif
            </a>

            {{-- User menu --}}
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-white/10 transition">
                    <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-sm font-bold text-white">
                        {{ strtoupper(substr(auth()->user()->first_name ?? 'U', 0, 1)) }}
                    </div>
                    <span class="hidden sm:block text-sm font-medium text-white">{{ auth()->user()->first_name ?? 'Account' }}</span>
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div x-show="open" @click.away="open = false" x-transition
                     class="absolute right-0 mt-2 w-56 bg-white text-gray-900 rounded-lg shadow-lg border py-2 z-50">
                    <div class="px-4 py-2 border-b">
                        <p class="font-semibold text-sm truncate">{{ auth()->user()->full_name }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
                    </div>

                    <a href="{{ route('portal.account.show') }}" class="block px-4 py-2 text-sm hover:bg-gray-50">Account Settings</a>

                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm hover:bg-gray-50 font-medium text-blue-600">Admin Panel</a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" class="border-t mt-1 pt-1">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50">Sign out</button>
                    </form>
                </div>
            </div>

            {{-- Mobile menu --}}
            <div class="md:hidden flex items-center gap-2" x-data="{ mobileOpen: false }">
                {{-- Mobile cart icon --}}
                <a href="{{ route('portal.cart.index') }}" class="relative p-2 text-gray-300 hover:text-white transition" title="View Cart">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                    </svg>
                    @if ($cartItemCount > 0)
                        <span class="absolute top-0 right-0 inline-flex items-center justify-center h-4 min-w-[1rem] px-0.5 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none ring-2 ring-slate-950">
                            {{ $cartItemCount }}
                        </span>
                    @endif
                </a>

                <button @click="mobileOpen = !mobileOpen" class="text-white p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div x-show="mobileOpen" x-transition class="absolute top-full left-0 right-0 bg-slate-900 border-t border-white/10 py-2 z-50">
                    @foreach ($nav as $item)
                        <a href="{{ route($item['route']) }}" class="block px-4 py-2 text-sm text-gray-300 hover:text-white hover:bg-white/5">{{ $item['label'] }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</header>
