<x-portal-layout>
    <x-slot:title>Domains</x-slot:title>

    <h1 class="text-3xl font-bold tracking-tight mb-2">Domains</h1>
    <p class="text-gray-500 mb-6">Domains registered and managed through {{ \App\Models\Setting::get('site_name', 'CK Enterprises') }}.</p>

    <div class="bg-white rounded-lg border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-5 py-3 text-left font-semibold text-gray-600">Domain</th>
                    <th class="px-5 py-3 text-left font-semibold text-gray-600">Expiry</th>
                    <th class="px-5 py-3 text-left font-semibold text-gray-600">Renewal</th>
                    <th class="px-5 py-3 text-left font-semibold text-gray-600">Registrar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($domains as $domain)
                    @php
                        $daysLeft = $domain->expiry_date ? (int) now()->diffInDays($domain->expiry_date, false) : null;
                        $urgency = $daysLeft !== null && $daysLeft < 0 ? 'text-red-600' : ($daysLeft !== null && $daysLeft <= 30 ? 'text-amber-600' : 'text-gray-700');
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-4 font-medium">{{ $domain->domain_name }}</td>
                        <td class="px-5 py-4 {{ $urgency }}">{{ $domain->expiry_date?->format('M j, Y') ?? '—' }}</td>
                        <td class="px-5 py-4">
                            @if ($domain->auto_renew)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Auto-renew</span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-700">Manual</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-gray-500">{{ $domain->registrar ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-8 text-center text-gray-500">
                            You don't currently have any domains registered through us. Your domain may be managed by a third-party registrar.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $domains->links() }}</div>
</x-portal-layout>
