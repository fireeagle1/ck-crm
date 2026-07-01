<x-portal-layout>
    <x-slot:title>Dashboard</x-slot:title>

    {{-- Greeting --}}
    <section class="mb-6">
        <h1 class="text-2xl font-semibold">
            {{ now()->hour < 12 ? 'Good Morning' : (now()->hour < 18 ? 'Good Afternoon' : 'Good Evening') }}, {{ auth()->user()->first_name ?? 'there' }}.
        </h1>
    </section>

    {{-- Support Plan banner --}}
    <section class="bg-white rounded-lg border-l-4 border-gray-900 p-5 shadow-sm mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Support Plan</p>
                <h2 class="text-lg font-semibold mt-1">Technical Support</h2>
                <p class="text-sm text-gray-600 mt-1">
                    @if ($hasSupportPlan)
                        Your account includes the Technical Support Package. Track requests and manage support work.
                    @else
                        Website changes, hardware support, and day-to-day technical support. Starting from £45/month.
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('portal.tickets.index') }}" class="inline-flex items-center px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">
                    Support Tickets
                </a>
                <a href="{{ route('portal.tickets.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                    Open Ticket
                </a>
            </div>
        </div>
    </section>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <p class="text-sm text-gray-500">Active Services</p>
            <p class="text-3xl font-semibold mt-1">{{ $activeServices }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <p class="text-sm text-gray-500">Open Tickets</p>
            <p class="text-3xl font-semibold mt-1">{{ $openTickets }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <p class="text-sm text-gray-500">Domains Expiring (30d)</p>
            <p class="text-3xl font-semibold mt-1 {{ $expiringDomains > 0 ? 'text-amber-600' : '' }}">{{ $expiringDomains }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <p class="text-sm text-gray-500">Quick Actions</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <a href="{{ route('portal.tickets.create') }}" class="text-sm text-blue-600 hover:underline">New ticket</a>
                <span class="text-gray-300">·</span>
                <a href="{{ route('portal.services.index') }}" class="text-sm text-blue-600 hover:underline">My services</a>
            </div>
        </div>
    </div>

    {{-- My Websites --}}
    @if ($websites->isNotEmpty())
        <section class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold">My Websites</h2>
                <a href="{{ route('portal.services.index') }}" class="text-sm text-blue-600 hover:underline">View all services</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($websites as $site)
                    <div class="bg-white rounded-lg shadow-sm border p-5">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ $site->domain_name ?? $site->service_short }}</h3>
                                @if ($site->domain_name && $site->domain_name !== $site->service_short)
                                    <p class="text-xs text-gray-400 mt-0.5">{{ $site->service_short }}</p>
                                @endif
                            </div>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Active</span>
                        </div>

                        {{-- Domain info if we manage it --}}
                        @php
                            $matchedDomain = $site->domain_name ? $customerDomains->firstWhere('domain_name', strtolower($site->domain_name)) : null;
                        @endphp
                        @if ($matchedDomain)
                            <div class="flex items-center gap-3 text-xs text-gray-500 mb-3 pb-3 border-b">
                                <span>Expires {{ $matchedDomain->expiry_date?->format('M j, Y') }}</span>
                                @if ($matchedDomain->auto_renew)
                                    <span class="inline-flex items-center rounded-full px-1.5 py-0.5 bg-green-50 text-green-600">Auto-renew</span>
                                @endif
                            </div>
                        @endif

                        {{-- Quick access buttons --}}
                        <div class="flex flex-wrap gap-2">
                            @if ($site->cpanel_username)
                                <form method="POST" action="{{ route('portal.services.sso.cpanel', $site) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-orange-500 text-white rounded text-xs font-medium hover:bg-orange-600 transition">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/></svg>
                                        cPanel
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('portal.services.sso.webmail', $site) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-blue-500 text-white rounded text-xs font-medium hover:bg-blue-600 transition">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        Webmail
                                    </button>
                                </form>
                            @endif
                            @if ($site->domain_name)
                                <a href="https://{{ $site->domain_name }}" target="_blank"
                                   class="inline-flex items-center gap-1 px-2.5 py-1.5 border rounded text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    Visit Site
                                </a>
                            @endif
                            <a href="{{ route('portal.services.show', $site) }}"
                               class="inline-flex items-center gap-1 px-2.5 py-1.5 border rounded text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                                Details
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Recent Tickets --}}
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="px-5 py-3 border-b flex items-center justify-between">
                <h2 class="text-lg font-semibold">Recent Tickets</h2>
                <a href="{{ route('portal.tickets.index') }}" class="text-sm text-blue-600 hover:underline">View all</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse ($recentTickets as $ticket)
                    <div class="px-5 py-3">
                        <div class="flex items-center justify-between">
                            <a href="{{ route('portal.tickets.show', $ticket) }}" class="text-sm font-medium text-blue-600 hover:underline">
                                INC{{ $ticket->ticket_id }}
                            </a>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $ticket->status === 'Open' ? 'bg-green-100 text-green-700' : ($ticket->status === 'Closed' ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ $ticket->status }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-700 mt-0.5">{{ Str::limit($ticket->subject, 50) }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Updated {{ $ticket->updated_at->diffForHumans() }}</p>
                    </div>
                @empty
                    <p class="px-5 py-4 text-sm text-gray-500">No tickets yet.</p>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            {{-- Upcoming Renewals --}}
            @if ($upcomingRenewals->isNotEmpty())
                <div class="bg-white rounded-lg shadow-sm border">
                    <div class="px-5 py-3 border-b">
                        <h2 class="text-lg font-semibold">Upcoming Renewals</h2>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach ($upcomingRenewals as $service)
                            <div class="px-5 py-3 flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium">{{ $service->service_short }}</p>
                                    <p class="text-xs text-gray-400">£{{ number_format($service->service_monthly_charge, 2) }}/{{ strtolower($service->service_payment_frequency ?? 'month') }}</p>
                                </div>
                                <span class="text-sm text-amber-600 font-medium">
                                    {{ $service->next_payment_date->format('M j') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Expiring Domains --}}
            @if ($expiringDomainsList->isNotEmpty())
                <div class="bg-white rounded-lg shadow-sm border">
                    <div class="px-5 py-3 border-b">
                        <h2 class="text-lg font-semibold">Domains Expiring Soon</h2>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach ($expiringDomainsList as $domain)
                            <div class="px-5 py-3 flex items-center justify-between">
                                <p class="text-sm font-medium">{{ $domain->domain_name }}</p>
                                <span class="text-sm text-amber-600 font-medium">
                                    {{ $domain->expiry_date->format('M j, Y') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Recent Invoices --}}
            @if ($recentInvoices->isNotEmpty())
                <div class="bg-white rounded-lg shadow-sm border">
                    <div class="px-5 py-3 border-b">
                        <h2 class="text-lg font-semibold">Recent Invoices</h2>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach ($recentInvoices as $invoice)
                            <div class="px-5 py-3 flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium">£{{ number_format($invoice->invoice_amount, 2) }}</p>
                                    <p class="text-xs text-gray-400">{{ $invoice->invoice_date?->format('M j, Y') }}</p>
                                </div>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $invoice->invoice_status === 'Paid' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $invoice->invoice_status }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-portal-layout>
