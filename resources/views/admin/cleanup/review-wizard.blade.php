@push('wide-layout')wide@endpush

<x-admin-layout>
    <x-slot:title>Service Review Wizard</x-slot:title>

    <h1 class="text-2xl font-bold mb-2">Service Review Wizard</h1>
    <p class="text-gray-500 mb-6">Step through each customer to review their services, remove duplicates, and confirm correct mappings.</p>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        {{-- Customer list sidebar --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg border sticky top-20 max-h-[75vh] overflow-y-auto">
                <div class="px-4 py-3 border-b">
                    <h2 class="text-sm font-bold">Customers ({{ $customers->count() }})</h2>
                </div>
                <ul class="divide-y">
                    @foreach ($customers as $c)
                        <li>
                            <a href="{{ route('admin.cleanup.review', ['customer' => $c->company_id]) }}"
                               class="block px-3 py-2 text-xs hover:bg-gray-50 {{ $current && $current->company_id == $c->company_id ? 'bg-blue-50 text-blue-700 font-semibold' : '' }}">
                                {{ Str::limit($c->company_name ?: $c->customer_name, 25) }}
                                <span class="text-gray-400 ml-1">({{ $c->services_count }})</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Main review area --}}
        <div class="lg:col-span-4">
            @if (!$current)
                <div class="bg-white rounded-lg border p-8 text-center text-gray-500">
                    <p>Select a customer from the list to review their services.</p>
                </div>
            @else
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold">{{ $current->company_name ?: $current->customer_name }}</h2>
                        <p class="text-sm text-gray-500">
                            Stripe: <span class="font-mono text-xs">{{ $current->stripe_customer_id ?? 'Not linked' }}</span>
                        </p>
                    </div>
                    <a href="{{ route('admin.customers.show', $current) }}" class="text-sm text-blue-600 hover:underline">View customer &rarr;</a>
                </div>

                {{-- Domains for this customer --}}
                @if ($domains->isNotEmpty())
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <h3 class="text-sm font-bold text-blue-900 mb-2">Domains Table ({{ $domains->count() }})</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($domains as $domain)
                                <span class="inline-flex items-center bg-white border rounded px-2 py-1 text-xs font-mono">
                                    {{ $domain->domain_name }}
                                    @if ($domain->expiry_date)
                                        <span class="text-gray-400 ml-1">exp {{ $domain->expiry_date->format('M Y') }}</span>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Services --}}
                <form method="POST" action="{{ route('admin.cleanup.review.delete') }}" onsubmit="return confirm('Delete selected services? This cannot be undone.')">
                    @csrf

                    <div class="bg-white rounded-lg border overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-3 py-3 w-8">
                                        <input type="checkbox" onclick="this.closest('table').querySelectorAll('input[name=\'service_ids[]\']').forEach(c=>c.checked=this.checked)" class="rounded">
                                    </th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600 text-xs">Service</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600 text-xs">Type</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600 text-xs">Domain</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600 text-xs">cPanel</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600 text-xs">Stripe Sub</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600 text-xs">£/mo</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600 text-xs">Status</th>
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600 text-xs">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($services as $service)
                                    @php
                                        $isDuplicate = $service->domain_name && $services->where('domain_name', $service->domain_name)->count() > 1;
                                        $isDomainOnly = !$service->cpanel_username && !$service->stripe_subscription_id && (!$service->service_monthly_charge || $service->service_monthly_charge <= 0);
                                        $alreadyInDomains = $service->domain_name && $domains->contains('domain_name', strtolower($service->domain_name));
                                        $isDuplicateSub = $service->stripe_subscription_id && $services->where('stripe_subscription_id', $service->stripe_subscription_id)->count() > 1;
                                    @endphp
                                    <tr class="{{ $isDuplicate ? 'bg-amber-50' : ($isDomainOnly ? 'bg-red-50' : '') }}">
                                        <td class="px-3 py-2">
                                            <input type="checkbox" name="service_ids[]" value="{{ $service->service_id }}" class="rounded">
                                        </td>
                                        <td class="px-3 py-2 font-medium text-xs">{{ Str::limit($service->service_short, 30) }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-500">{{ $service->service_type ?? '—' }}</td>
                                        <td class="px-3 py-2 font-mono text-xs">
                                            {{ $service->domain_name ?? '—' }}
                                            @if ($alreadyInDomains)
                                                <span class="text-green-600 text-xs">✓ in domains</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 font-mono text-xs">{{ $service->cpanel_username ?? '—' }}</td>
                                        <td class="px-3 py-2 font-mono text-xs">
                                            {{ $service->stripe_subscription_id ? Str::limit($service->stripe_subscription_id, 12) : '—' }}
                                            @if ($isDuplicateSub)
                                                <span class="text-red-600 font-semibold">⚠ DUP</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-xs">{{ $service->service_monthly_charge ? '£' . number_format($service->service_monthly_charge, 2) : '—' }}</td>
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                                {{ $service->status === 'Active' ? 'bg-green-100 text-green-700' : ($service->status === 'Cancelled' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600') }}">
                                                {{ $service->status }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <div class="flex gap-1">
                                                <a href="{{ route('admin.services.edit', $service) }}" class="text-blue-600 hover:underline text-xs">Edit</a>
                                                @if ($service->domain_name && !$alreadyInDomains)
                                                    <form method="POST" action="{{ route('admin.cleanup.review.move-to-domain') }}" class="inline">
                                                        @csrf
                                                        <input type="hidden" name="service_id" value="{{ $service->service_id }}">
                                                        <input type="hidden" name="customer" value="{{ $current->company_id }}">
                                                        <button type="submit" class="text-green-600 hover:underline text-xs">→ Domain</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 flex items-center justify-between">
                        <div class="flex gap-3 text-xs">
                            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-50 border border-amber-200"></span> Duplicate domain</span>
                            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-50 border border-red-200"></span> No hosting/billing</span>
                            <span class="inline-flex items-center gap-1 text-green-600">✓ Already in domains table</span>
                            <span class="inline-flex items-center gap-1 text-red-600">⚠ DUP = Same Stripe subscription on multiple services</span>
                        </div>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-semibold hover:bg-red-700">
                            Delete Selected
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-admin-layout>
