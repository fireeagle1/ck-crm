<x-portal-layout>
    <x-slot:title>Dashboard</x-slot:title>

    {{-- Welcome --}}
    <div class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight">
            {{ now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening') }}, {{ auth()->user()->first_name ?? 'there' }}.
        </h1>
        <p class="text-gray-500 mt-1">Here's an overview of your account with {{ \App\Models\Setting::get('site_name', 'CK Enterprises') }}.</p>
    </div>

    {{-- KPI strip --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg p-5 border">
            <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">Services</p>
            <p class="text-3xl font-bold mt-1">{{ $activeServices }}</p>
            <a href="{{ route('portal.services.index') }}" class="text-sm text-blue-600 hover:underline mt-1 inline-block">View all &rarr;</a>
        </div>
        <div class="bg-white rounded-lg p-5 border">
            <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">Open Tickets</p>
            <p class="text-3xl font-bold mt-1 {{ $openTickets > 0 ? 'text-amber-600' : '' }}">{{ $openTickets }}</p>
            <a href="{{ route('portal.tickets.index') }}" class="text-sm text-blue-600 hover:underline mt-1 inline-block">View all &rarr;</a>
        </div>
        <div class="bg-white rounded-lg p-5 border">
            <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">Domains</p>
            <p class="text-3xl font-bold mt-1">{{ $customerDomains->count() }}</p>
            @if ($expiringDomains > 0)
                <p class="text-xs text-amber-600 font-medium mt-1">{{ $expiringDomains }} expiring soon</p>
            @else
                <a href="{{ route('portal.domains.index') }}" class="text-sm text-blue-600 hover:underline mt-1 inline-block">View all &rarr;</a>
            @endif
        </div>
        <div class="bg-white rounded-lg p-5 border">
            <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">Quick Actions</p>
            <div class="mt-2 space-y-1">
                <a href="{{ route('portal.tickets.create') }}" class="block text-sm text-blue-600 hover:underline font-medium">+ New ticket</a>
                <form action="{{ route('portal.billing.portal') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="text-sm text-blue-600 hover:underline font-medium">Manage billing</button>
                </form>
            </div>
        </div>
    </div>

    {{-- My Websites --}}
    @if ($websites->isNotEmpty())
        <section class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold">My Websites</h2>
                <a href="{{ route('portal.services.index') }}" class="text-sm text-blue-600 hover:underline font-medium">All services &rarr;</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($websites as $site)
                    <div class="bg-white rounded-lg border p-5 hover:shadow-md transition">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h3 class="font-bold text-gray-900">{{ $site->domain_name ?? $site->service_short }}</h3>
                                @if ($site->domain_name && $site->domain_name !== $site->service_short)
                                    <p class="text-xs text-gray-400">{{ $site->service_short }}</p>
                                @endif
                            </div>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-green-100 text-green-700">Active</span>
                        </div>

                        @php
                            $matchedDomain = $site->domain_name ? $customerDomains->firstWhere('domain_name', strtolower($site->domain_name)) : null;
                        @endphp
                        @if ($matchedDomain)
                            <p class="text-xs text-gray-500 mb-3">
                                Expires {{ $matchedDomain->expiry_date?->format('M j, Y') }}
                                @if ($matchedDomain->auto_renew)
                                    · <span class="text-green-600">Auto-renew</span>
                                @endif
                            </p>
                        @endif

                        <div class="flex flex-wrap gap-2 pt-3 border-t">
                            @if ($site->cpanel_username)
                                <form method="POST" action="{{ route('portal.services.sso.cpanel', $site) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-orange-500 text-white rounded text-xs font-semibold hover:bg-orange-600 transition">cPanel</button>
                                </form>
                                <form method="POST" action="{{ route('portal.services.sso.webmail', $site) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white rounded text-xs font-semibold hover:bg-blue-700 transition">Webmail</button>
                                </form>
                            @endif
                            @if ($site->domain_name)
                                <a href="https://{{ $site->domain_name }}" target="_blank" class="px-3 py-1.5 border rounded text-xs font-semibold text-gray-700 hover:bg-gray-50 transition">Visit Site</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Two-column: Tickets + Invoices --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- Recent Tickets --}}
        <div class="bg-white rounded-lg border">
            <div class="px-5 py-4 border-b flex items-center justify-between">
                <h2 class="font-bold">Recent Tickets</h2>
                <a href="{{ route('portal.tickets.index') }}" class="text-sm text-blue-600 hover:underline">View all</a>
            </div>
            @if ($recentTickets->isEmpty())
                <div class="px-5 py-8 text-center">
                    <p class="text-gray-400 text-sm">No tickets yet</p>
                    <a href="{{ route('portal.tickets.create') }}" class="text-sm text-blue-600 hover:underline mt-2 inline-block">Open your first ticket &rarr;</a>
                </div>
            @else
                <div class="divide-y">
                    @foreach ($recentTickets as $ticket)
                        <a href="{{ route('portal.tickets.show', $ticket) }}" class="block px-5 py-3 hover:bg-gray-50 transition">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-blue-600">INC{{ $ticket->ticket_id }}</span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $ticket->status === 'Open' ? 'bg-green-100 text-green-700' : ($ticket->status === 'Closed' ? 'bg-gray-100 text-gray-600' : 'bg-amber-100 text-amber-700') }}">
                                    {{ $ticket->status }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-700 mt-0.5 truncate">{{ $ticket->subject }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $ticket->updated_at->diffForHumans() }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Recent Invoices --}}
        <div class="bg-white rounded-lg border">
            <div class="px-5 py-4 border-b flex items-center justify-between">
                <h2 class="font-bold">Recent Invoices</h2>
                <a href="{{ route('portal.invoices.index') }}" class="text-sm text-blue-600 hover:underline">View all</a>
            </div>
            @if ($recentInvoices->isEmpty())
                <div class="px-5 py-8 text-center">
                    <p class="text-gray-400 text-sm">No invoices yet</p>
                </div>
            @else
                <div class="divide-y">
                    @foreach ($recentInvoices as $invoice)
                        <div class="px-5 py-3 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold">£{{ number_format($invoice->invoice_amount, 2) }}</p>
                                <p class="text-xs text-gray-400">{{ $invoice->invoice_date?->format('M j, Y') }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $invoice->invoice_status === 'Paid' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $invoice->invoice_status }}
                                </span>
                                @if ($invoice->stripe_hosted_url)
                                    <a href="{{ $invoice->stripe_hosted_url }}" target="_blank" class="text-xs text-blue-600 hover:underline">
                                        {{ $invoice->invoice_status === 'Paid' ? 'View' : 'Pay' }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Bottom row: Renewals + Domains --}}
    @if ($upcomingRenewals->isNotEmpty() || $expiringDomainsList->isNotEmpty())
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @if ($upcomingRenewals->isNotEmpty())
                <div class="bg-white rounded-lg border">
                    <div class="px-5 py-4 border-b">
                        <h2 class="font-bold">Upcoming Renewals</h2>
                    </div>
                    <div class="divide-y">
                        @foreach ($upcomingRenewals as $service)
                            <div class="px-5 py-3 flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium">{{ $service->service_short }}</p>
                                    <p class="text-xs text-gray-400">£{{ number_format($service->service_monthly_charge, 2) }}/{{ strtolower($service->service_payment_frequency ?? 'month') }}</p>
                                </div>
                                <span class="text-sm font-semibold text-amber-600">{{ $service->next_payment_date->format('M j') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($expiringDomainsList->isNotEmpty())
                <div class="bg-white rounded-lg border">
                    <div class="px-5 py-4 border-b">
                        <h2 class="font-bold">Domains Expiring Soon</h2>
                    </div>
                    <div class="divide-y">
                        @foreach ($expiringDomainsList as $domain)
                            <div class="px-5 py-3 flex items-center justify-between">
                                <p class="text-sm font-medium">{{ $domain->domain_name }}</p>
                                <span class="text-sm font-semibold text-amber-600">{{ $domain->expiry_date->format('M j, Y') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Support plan CTA --}}
    @if (!$hasSupportPlan)
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h3 class="font-bold text-blue-900">Technical Support Package</h3>
                    <p class="text-sm text-blue-700 mt-1">Website changes, hardware support, and day-to-day technical support. Starting from £45/month.</p>
                </div>
                <a href="{{ route('portal.tickets.create') }}" class="inline-flex items-center px-4 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition shrink-0">
                    Enquire Now
                </a>
            </div>
        </div>
    @endif
</x-portal-layout>
