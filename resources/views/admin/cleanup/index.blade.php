<x-admin-layout>
    <x-slot:title>Data Cleanup</x-slot:title>

    <h1 class="text-2xl font-bold mb-2">Data Cleanup</h1>
    <p class="text-gray-500 mb-6">Review and remove legacy data, duplicates, and orphaned records.</p>

    {{-- Cancelled services --}}
    @if ($cancelledServices->isNotEmpty())
        <div class="bg-white rounded-lg border mb-6">
            <div class="px-5 py-4 border-b">
                <h2 class="font-bold">Cancelled Services ({{ $cancelledServices->count() }})</h2>
                <p class="text-xs text-gray-500">These services are cancelled. Select any to permanently delete.</p>
            </div>
            <form method="POST" action="{{ route('admin.cleanup.delete-services') }}" onsubmit="return confirm('Delete selected services? This cannot be undone.')">
                @csrf
                <div class="max-h-64 overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b sticky top-0">
                            <tr>
                                <th class="px-5 py-2 text-left w-8"><input type="checkbox" onclick="this.closest('table').querySelectorAll('input[type=checkbox]').forEach(c=>c.checked=this.checked)" class="rounded"></th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">Service</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">Customer</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">End Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($cancelledServices as $service)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-2"><input type="checkbox" name="service_ids[]" value="{{ $service->service_id }}" class="rounded"></td>
                                    <td class="px-3 py-2">{{ $service->service_short }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $service->customer?->company_name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-400">{{ $service->end_date?->format('Y-m-d') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3 border-t bg-gray-50">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-semibold hover:bg-red-700">Delete Selected</button>
                </div>
            </form>
        </div>
    @endif

    {{-- Domain-type services (services that are really just domain records) --}}
    @if ($domainServices->isNotEmpty())
        <div class="bg-white rounded-lg border mb-6">
            <div class="px-5 py-4 border-b">
                <h2 class="font-bold">Services That Look Like Domains ({{ $domainServices->count() }})</h2>
                <p class="text-xs text-gray-500">These services have no hosting, no charge, and appear to just be domain registrations. Consider deleting if the domain is already in the Domains table.</p>
            </div>
            <form method="POST" action="{{ route('admin.cleanup.delete-services') }}" onsubmit="return confirm('Delete selected services?')">
                @csrf
                <div class="max-h-64 overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b sticky top-0">
                            <tr>
                                <th class="px-5 py-2 text-left w-8"><input type="checkbox" onclick="this.closest('table').querySelectorAll('input[type=checkbox]').forEach(c=>c.checked=this.checked)" class="rounded"></th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">Service Name</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">Customer</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">Type</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($domainServices as $service)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-2"><input type="checkbox" name="service_ids[]" value="{{ $service->service_id }}" class="rounded"></td>
                                    <td class="px-3 py-2">{{ $service->service_short }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $service->customer?->company_name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-400">{{ $service->service_type ?? 'Not set' }}</td>
                                    <td class="px-3 py-2 text-gray-400">{{ $service->status }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3 border-t bg-gray-50">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-semibold hover:bg-red-700">Delete Selected</button>
                </div>
            </form>
        </div>
    @endif

    {{-- Old expired domains --}}
    @if ($oldExpiredDomains->isNotEmpty())
        <div class="bg-white rounded-lg border mb-6">
            <div class="px-5 py-4 border-b">
                <h2 class="font-bold">Expired Domains (over 1 year) — {{ $oldExpiredDomains->count() }}</h2>
                <p class="text-xs text-gray-500">These domains expired over a year ago and are hidden from customers. Safe to delete.</p>
            </div>
            <form method="POST" action="{{ route('admin.cleanup.delete-domains') }}" onsubmit="return confirm('Delete selected domains?')">
                @csrf
                <div class="max-h-64 overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b sticky top-0">
                            <tr>
                                <th class="px-5 py-2 text-left w-8"><input type="checkbox" onclick="this.closest('table').querySelectorAll('input[type=checkbox]').forEach(c=>c.checked=this.checked)" class="rounded"></th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">Domain</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">Customer</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">Expired</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($oldExpiredDomains as $domain)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-2"><input type="checkbox" name="domain_ids[]" value="{{ $domain->id }}" class="rounded"></td>
                                    <td class="px-3 py-2">{{ $domain->domain_name }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $domain->customer?->company_name ?? 'Unassigned' }}</td>
                                    <td class="px-3 py-2 text-red-500">{{ $domain->expiry_date?->format('Y-m-d') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3 border-t bg-gray-50">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-semibold hover:bg-red-700">Delete Selected</button>
                </div>
            </form>
        </div>
    @endif

    {{-- Orphaned services --}}
    @if ($orphanedServices->isNotEmpty())
        <div class="bg-white rounded-lg border mb-6">
            <div class="px-5 py-4 border-b">
                <h2 class="font-bold">Orphaned Services ({{ $orphanedServices->count() }})</h2>
                <p class="text-xs text-gray-500">These services reference a customer that no longer exists.</p>
            </div>
            <form method="POST" action="{{ route('admin.cleanup.delete-services') }}" onsubmit="return confirm('Delete selected?')">
                @csrf
                <div class="max-h-48 overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y">
                            @foreach ($orphanedServices as $service)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-2"><input type="checkbox" name="service_ids[]" value="{{ $service->service_id }}" class="rounded"></td>
                                    <td class="px-3 py-2">{{ $service->service_short }}</td>
                                    <td class="px-3 py-2 text-red-500">Company ID: {{ $service->company_id ?? 'null' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3 border-t bg-gray-50">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-semibold hover:bg-red-700">Delete Selected</button>
                </div>
            </form>
        </div>
    @endif

    {{-- All clean --}}
    @if ($cancelledServices->isEmpty() && $domainServices->isEmpty() && $oldExpiredDomains->isEmpty() && $orphanedServices->isEmpty())
        <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
            <p class="text-green-800 font-semibold">All clear — no legacy data to clean up.</p>
        </div>
    @endif
</x-admin-layout>
