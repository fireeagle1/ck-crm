<x-admin-layout>
    <x-slot:title>{{ $customer->company_name }}</x-slot:title>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">{{ $customer->company_name }}</h1>
        <div class="flex gap-2">
            <a href="{{ route('admin.customers.edit', $customer) }}" class="inline-flex items-center px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">
                Edit
            </a>
            @if ($customer->services->where('status', 'Active')->isEmpty())
                <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}"
                      onsubmit="return confirm('Delete {{ $customer->company_name }}? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-red-200 text-red-600 rounded-md text-sm font-medium hover:bg-red-50">
                        Delete
                    </button>
                </form>
            @endif
            <a href="{{ route('admin.customers.index') }}" class="text-sm text-blue-600 hover:underline self-center">&larr; Customers</a>
        </div>
    </div>

    {{-- Overview cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <p class="text-sm text-gray-500">Services</p>
            <p class="text-3xl font-semibold mt-1">{{ $customer->services->count() }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <p class="text-sm text-gray-500">Users</p>
            <p class="text-3xl font-semibold mt-1">{{ $customer->users->count() }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <p class="text-sm text-gray-500">Tickets</p>
            <p class="text-3xl font-semibold mt-1">{{ $customer->tickets->count() }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <p class="text-sm text-gray-500">Total Income</p>
            <p class="text-3xl font-semibold mt-1">£{{ number_format($totalIncome, 2) }}</p>
        </div>
    </div>

    {{-- Company details --}}
    <div class="bg-white rounded-lg shadow-sm border p-5 mb-6">
        <h2 class="text-lg font-semibold mb-3">Company Details</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
                <dt class="text-gray-500">Contact Name</dt>
                <dd class="font-medium">{{ $customer->customer_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Phone</dt>
                <dd class="font-medium">{{ $customer->phone_number ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Address</dt>
                <dd class="font-medium">
                    {{ collect([$customer->address_line1, $customer->address_line2, $customer->city, $customer->state, $customer->postal_code, $customer->country])->filter()->implode(', ') ?: '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Stripe ID</dt>
                <dd class="font-medium font-mono text-xs">{{ $customer->stripe_customer_id ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Services --}}
    <div class="bg-white rounded-lg shadow-sm border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b">
            <h2 class="text-lg font-semibold">Services</h2>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Service</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Monthly</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Frequency</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($customer->services as $service)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">{{ $service->service_short }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $service->status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $service->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ $service->service_monthly_charge ? '£' . number_format($service->service_monthly_charge, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $service->service_payment_frequency ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-4 text-center text-gray-500">No services.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Users --}}
    <div class="bg-white rounded-lg shadow-sm border overflow-hidden mb-6">
        <div class="px-5 py-3 border-b">
            <h2 class="text-lg font-semibold">Users</h2>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Name</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Email</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Last Login</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($customer->users as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">{{ $user->full_name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $user->email }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $user->last_login?->diffForHumans() ?? 'Never' }}</td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('admin.users.impersonate', $user) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-blue-600 hover:underline text-sm">Impersonate</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-4 text-center text-gray-500">No users.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Recent Tickets --}}
    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <div class="px-5 py-3 border-b">
            <h2 class="text-lg font-semibold">Recent Tickets</h2>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">ID</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Subject</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($customer->tickets->take(10) as $ticket)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.tickets.show', $ticket) }}" class="text-blue-600 hover:underline">INC{{ $ticket->ticket_id }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $ticket->subject }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $ticket->status === 'Open' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                                {{ $ticket->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $ticket->created_at->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-4 text-center text-gray-500">No tickets.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
