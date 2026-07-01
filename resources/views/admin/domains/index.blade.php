<x-admin-layout>
    <x-slot:title>Domains</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Domains</h1>
        <a href="{{ route('admin.domains.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
            Add Domain
        </a>
    </div>

    {{-- Filter pills --}}
    <div class="flex gap-2 mb-4">
        <a href="{{ route('admin.domains.index', ['filter' => 'all']) }}"
           class="px-3 py-1.5 rounded-md text-sm font-medium {{ $filter === 'all' ? 'bg-blue-600 text-white' : 'border hover:bg-gray-50' }}">
            All ({{ $totalDomains }})
        </a>
        <a href="{{ route('admin.domains.index', ['filter' => 'expiring']) }}"
           class="px-3 py-1.5 rounded-md text-sm font-medium {{ $filter === 'expiring' ? 'bg-amber-600 text-white' : 'border hover:bg-gray-50' }}">
            Expiring ({{ $expiringCount }})
        </a>
        <a href="{{ route('admin.domains.index', ['filter' => 'expired']) }}"
           class="px-3 py-1.5 rounded-md text-sm font-medium {{ $filter === 'expired' ? 'bg-red-600 text-white' : 'border hover:bg-gray-50' }}">
            Expired ({{ $expiredCount }})
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Domain</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Customer</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Registrar</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Expiry</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Cost</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($domains as $domain)
                    @php
                        $daysLeft = $domain->expiry_date ? (int) now()->diffInDays($domain->expiry_date, false) : null;
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ $domain->domain_name }}</td>
                        <td class="px-4 py-3 text-gray-500">
                            @if ($domain->customer)
                                <a href="{{ route('admin.customers.show', $domain->customer) }}" class="text-blue-600 hover:underline">
                                    {{ $domain->customer->company_name }}
                                </a>
                            @else
                                Unassigned
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $domain->registrar ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($domain->expiry_date)
                                <span class="{{ $daysLeft < 0 ? 'text-red-600 font-medium' : ($daysLeft <= 30 ? 'text-amber-600 font-medium' : 'text-gray-700') }}">
                                    {{ $domain->expiry_date->format('Y-m-d') }}
                                    <span class="text-xs">({{ $daysLeft < 0 ? 'expired' : $daysLeft . 'd' }})</span>
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $domain->cost ? '£' . number_format($domain->cost, 2) : '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <a href="{{ route('admin.domains.edit', $domain) }}" class="text-blue-600 hover:underline text-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.domains.destroy', $domain) }}" class="inline"
                                      onsubmit="return confirm('Delete {{ $domain->domain_name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline text-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No domains.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $domains->links() }}</div>
</x-admin-layout>
