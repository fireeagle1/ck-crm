<x-portal-layout>
    <x-slot:title>Domains</x-slot:title>

    <h1 class="text-2xl font-semibold mb-4">Domains</h1>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Domain</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Expiry</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Renewal</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Registrar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($domains as $domain)
                    @php
                        $daysLeft = now()->diffInDays($domain->expiry_date, false);
                        $urgency = $daysLeft < 0 ? 'text-red-600' : ($daysLeft <= 30 ? 'text-amber-600' : 'text-gray-700');
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ $domain->domain_name }}</td>
                        <td class="px-4 py-3 {{ $urgency }}">{{ $domain->expiry_date?->format('M j, Y') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($domain->auto_renew)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Auto-renew</span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-700">Manual</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $domain->registrar ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">No domains.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $domains->links() }}</div>
</x-portal-layout>
