<x-admin-layout>
    <x-slot:title>Admin Dashboard</x-slot:title>

    <h1 class="text-2xl font-semibold mb-6">Dashboard</h1>

    {{-- Revenue KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg p-5 shadow-sm border">
            <p class="text-sm text-gray-500">Monthly Recurring Revenue</p>
            <p class="text-3xl font-semibold mt-1">£{{ number_format($mrr, 2) }}</p>
            <p class="text-xs text-gray-400 mt-1">ARR: £{{ number_format($arr, 2) }}</p>
        </div>
        <div class="bg-white rounded-lg p-5 shadow-sm border">
            <p class="text-sm text-gray-500">Revenue This Month</p>
            <p class="text-3xl font-semibold mt-1">£{{ number_format($revenueThisMonth, 2) }}</p>
        </div>
        <div class="bg-white rounded-lg p-5 shadow-sm border">
            <p class="text-sm text-gray-500">Overdue Invoices</p>
            <p class="text-3xl font-semibold mt-1 {{ $overdueInvoices > 0 ? 'text-red-600' : '' }}">{{ $overdueInvoices }}</p>
            @if ($overdueAmount > 0)
                <p class="text-xs text-red-500 mt-1">£{{ number_format($overdueAmount, 2) }} outstanding</p>
            @endif
        </div>
        <div class="bg-white rounded-lg p-5 shadow-sm border">
            <p class="text-sm text-gray-500">Active Customers</p>
            <p class="text-3xl font-semibold mt-1">{{ $totalCustomers }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $activeServices }} active services</p>
        </div>
    </div>

    {{-- Tickets & Quick stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg p-5 shadow-sm border">
            <p class="text-sm text-gray-500">Open Tickets</p>
            <p class="text-3xl font-semibold mt-1">{{ $openTickets }}</p>
            <div class="mt-2 space-y-1 text-xs">
                @if ($criticalTickets > 0)
                    <p class="text-red-600 font-medium">{{ $criticalTickets }} critical</p>
                @endif
                @if ($highTickets > 0)
                    <p class="text-orange-600 font-medium">{{ $highTickets }} high</p>
                @endif
                @if ($overdueTickets > 0)
                    <p class="text-red-500 font-medium">{{ $overdueTickets }} overdue</p>
                @endif
            </div>
        </div>
        <div class="bg-white rounded-lg p-5 shadow-sm border">
            <p class="text-sm text-gray-500">Avg. First Response</p>
            <p class="text-3xl font-semibold mt-1">
                @if ($avgResponseTime)
                    @if ($avgResponseTime < 60)
                        {{ round($avgResponseTime) }}m
                    @else
                        {{ round($avgResponseTime / 60, 1) }}h
                    @endif
                @else
                    —
                @endif
            </p>
            <p class="text-xs text-gray-400 mt-1">Average time to first reply</p>
        </div>
        <div class="bg-white rounded-lg p-5 shadow-sm border">
            <p class="text-sm text-gray-500">Quick Actions</p>
            <div class="mt-2 space-y-1">
                <a href="{{ route('admin.customers.create') }}" class="block text-sm text-blue-600 hover:underline">+ Add customer</a>
                <a href="{{ route('admin.services.create') }}" class="block text-sm text-blue-600 hover:underline">+ Add service</a>
                <a href="{{ route('admin.tickets.create') }}" class="block text-sm text-blue-600 hover:underline">+ Create ticket</a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Recent Tickets --}}
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="px-5 py-3 border-b flex items-center justify-between">
                <h2 class="text-lg font-semibold">Recent Tickets</h2>
                <a href="{{ route('admin.tickets.index') }}" class="text-sm text-blue-600 hover:underline">View all</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse ($recentTickets as $ticket)
                    <div class="px-5 py-3 flex items-center justify-between">
                        <div>
                            <a href="{{ route('admin.tickets.show', $ticket) }}" class="text-sm font-medium text-blue-600 hover:underline">
                                INC{{ $ticket->ticket_id }}
                            </a>
                            <span class="text-sm text-gray-700 ml-2">{{ Str::limit($ticket->subject, 40) }}</span>
                            <p class="text-xs text-gray-400">
                                @if ($ticket->customer)
                                    <a href="{{ route('admin.customers.show', $ticket->customer) }}" class="text-blue-600 hover:underline">{{ $ticket->customer->company_name }}</a>
                                @endif
                                · {{ $ticket->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                            {{ $ticket->priority === 'Critical' ? 'bg-red-100 text-red-700' : ($ticket->priority === 'High' ? 'bg-orange-100 text-orange-700' : 'bg-blue-100 text-blue-700') }}">
                            {{ $ticket->priority ?? 'Normal' }}
                        </span>
                    </div>
                @empty
                    <p class="px-5 py-4 text-sm text-gray-500">No tickets yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Expiring Domains + Recent Logins --}}
        <div class="space-y-6">
            @if ($expiringDomains->isNotEmpty())
                <div class="bg-white rounded-lg shadow-sm border">
                    <div class="px-5 py-3 border-b">
                        <h2 class="text-lg font-semibold">Domains Expiring Soon</h2>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach ($expiringDomains as $domain)
                            @php $daysLeft = (int) now()->diffInDays($domain->expiry_date, false); @endphp
                            <div class="px-5 py-3 flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium">{{ $domain->domain_name }}</p>
                                    <p class="text-xs text-gray-400">
                                        @if ($domain->customer)
                                            <a href="{{ route('admin.customers.show', $domain->customer) }}" class="text-blue-600 hover:underline">{{ $domain->customer->company_name }}</a>
                                        @endif
                                    </p>
                                </div>
                                <span class="text-sm font-medium {{ $daysLeft < 0 ? 'text-red-600' : ($daysLeft <= 7 ? 'text-red-500' : 'text-amber-600') }}">
                                    {{ $daysLeft < 0 ? 'Expired ' . abs($daysLeft) . 'd ago' : $daysLeft . 'd left' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm border">
                <div class="px-5 py-3 border-b">
                    <h2 class="text-lg font-semibold">Recent Logins</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse ($recentLogins as $login)
                        <div class="px-5 py-3 flex items-center justify-between">
                            <a href="{{ route('admin.users.edit', $login) }}" class="text-sm text-blue-600 hover:underline">{{ $login->full_name }}</a>
                            <span class="text-xs text-gray-500">{{ $login->last_login->diffForHumans() }}</span>
                        </div>
                    @empty
                        <p class="px-5 py-4 text-sm text-gray-500">No logins recorded.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
