<x-admin-layout>
    <x-slot:title>{{ $customer->company_name }}</x-slot:title>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">{{ $customer->company_name }}</h1>
        <div class="flex gap-2">
            <a href="{{ route('admin.customers.edit', $customer) }}" class="inline-flex items-center px-4 py-2 border rounded-md text-sm font-semibold hover:bg-gray-50">Edit</a>
            @if ($customer->services->where('status', 'Active')->isEmpty())
                <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}"
                      onsubmit="return confirm('Delete {{ $customer->company_name }}? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-red-200 text-red-600 rounded-md text-sm font-semibold hover:bg-red-50">Delete</button>
                </form>
            @endif
            <a href="{{ route('admin.customers.index') }}" class="text-sm text-blue-600 hover:underline self-center">&larr; Customers</a>
        </div>
    </div>

    {{-- Overdue warning --}}
    @if ($overdueInvoices > 0)
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="text-sm font-semibold text-red-800">⚠ This customer has {{ $overdueInvoices }} overdue invoice(s).</p>
        </div>
    @endif

    {{-- KPI strip --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg p-4 border">
            <p class="text-xs font-medium text-gray-500 uppercase">Services</p>
            <p class="text-2xl font-bold mt-1">{{ $customer->services->where('status', 'Active')->count() }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 border">
            <p class="text-xs font-medium text-gray-500 uppercase">Domains</p>
            <p class="text-2xl font-bold mt-1">{{ $customer->domains->count() }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 border">
            <p class="text-xs font-medium text-gray-500 uppercase">Users</p>
            <p class="text-2xl font-bold mt-1">{{ $customer->users->count() }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 border">
            <p class="text-xs font-medium text-gray-500 uppercase">Open Tickets</p>
            <p class="text-2xl font-bold mt-1">{{ $customer->tickets->where('status', '!=', 'Closed')->count() }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 border">
            <p class="text-xs font-medium text-gray-500 uppercase">Total Income</p>
            <p class="text-2xl font-bold mt-1">£{{ number_format($totalIncome, 2) }}</p>
        </div>
    </div>

    {{-- Company details --}}
    <div class="bg-white rounded-lg border p-5 mb-6">
        <h2 class="font-bold mb-3">Company Details</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
                <dt class="text-gray-500">Contact</dt>
                <dd class="font-medium">{{ $customer->customer_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Phone</dt>
                <dd class="font-medium">{{ $customer->phone_number ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Address</dt>
                <dd class="font-medium">{{ collect([$customer->address_line1, $customer->address_line2, $customer->city, $customer->postal_code])->filter()->implode(', ') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Stripe</dt>
                <dd class="font-mono text-xs">{{ $customer->stripe_customer_id ?? 'Not linked' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Services --}}
    <div class="bg-white rounded-lg border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b flex items-center justify-between">
            <h2 class="font-bold">Services ({{ $customer->services->count() }})</h2>
            <a href="{{ route('admin.services.create') }}" class="text-sm text-blue-600 hover:underline">+ Add</a>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Service</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Domain</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Billing</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Stripe Status</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($customer->services->sortByDesc('status') as $service)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium">{{ $service->service_short }}</td>
                        <td class="px-4 py-2 text-gray-500 text-xs font-mono">{{ $service->domain_name ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-600">
                            @if ($service->service_monthly_charge)
                                £{{ number_format($service->service_monthly_charge, 2) }}/{{ strtolower($service->service_payment_frequency ?? 'mo') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @if ($service->stripe_subscription_id)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Linked</span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-500">No sub</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                {{ $service->status === 'Active' ? 'bg-green-100 text-green-700' : ($service->status === 'Cancelled' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ $service->status }}
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <a href="{{ route('admin.services.show', $service) }}" class="text-blue-600 hover:underline text-xs">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-4 text-center text-gray-500">No services.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Domains --}}
    <div class="bg-white rounded-lg border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b flex items-center justify-between">
            <h2 class="font-bold">Domains ({{ $customer->domains->count() }})</h2>
            <a href="{{ route('admin.domains.create') }}" class="text-sm text-blue-600 hover:underline">+ Add</a>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Domain</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Registrar</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Expiry</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Renew</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Stripe</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($customer->domains as $domain)
                    @php $daysLeft = $domain->expiry_date ? (int) now()->diffInDays($domain->expiry_date, false) : null; @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium font-mono text-xs">{{ $domain->domain_name }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $domain->registrar ?? '—' }}</td>
                        <td class="px-4 py-2 {{ $daysLeft !== null && $daysLeft < 30 ? ($daysLeft < 0 ? 'text-red-600 font-semibold' : 'text-amber-600') : 'text-gray-600' }}">
                            {{ $domain->expiry_date?->format('M j, Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-2">
                            @if ($domain->auto_renew)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Auto</span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-500">Manual</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @if ($domain->stripe_subscription_id)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Linked</span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <a href="{{ route('admin.domains.edit', $domain) }}" class="text-blue-600 hover:underline text-xs">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-4 text-center text-gray-500">No domains.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Users --}}
    <div class="bg-white rounded-lg border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b">
            <h2 class="font-bold">Users ({{ $customer->users->count() }})</h2>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Name</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Email</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Last Login</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($customer->users as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium">{{ $user->full_name }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $user->email }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $user->last_login?->diffForHumans() ?? 'Never' }}</td>
                        <td class="px-4 py-2">
                            @if ($user->is_locked)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700">Disabled</span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Active</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('admin.users.impersonate', $user) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-blue-600 hover:underline text-xs">Impersonate</button>
                                </form>
                                <form method="POST" action="{{ route('admin.users.toggle-lock', $user) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-{{ $user->is_locked ? 'green' : 'red' }}-600 hover:underline text-xs">
                                        {{ $user->is_locked ? 'Enable' : 'Disable' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-4 text-center text-gray-500">No users.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Recent Tickets --}}
    <div class="bg-white rounded-lg border overflow-hidden">
        <div class="px-5 py-3 border-b flex items-center justify-between">
            <h2 class="font-bold">Recent Tickets</h2>
            <a href="{{ route('admin.tickets.index') }}" class="text-sm text-blue-600 hover:underline">View all</a>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">ID</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Subject</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($customer->tickets->sortByDesc('created_at')->take(10) as $ticket)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2"><a href="{{ route('admin.tickets.show', $ticket) }}" class="text-blue-600 hover:underline">INC{{ $ticket->ticket_id }}</a></td>
                        <td class="px-4 py-2">{{ $ticket->subject }}</td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $ticket->status === 'Open' ? 'bg-green-100 text-green-700' : ($ticket->status === 'Closed' ? 'bg-gray-100 text-gray-600' : 'bg-amber-100 text-amber-700') }}">
                                {{ $ticket->status }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-gray-500">{{ $ticket->created_at->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-4 text-center text-gray-500">No tickets.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
