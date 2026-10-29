<x-admin-layout>
    <x-slot:title>{{ $customerApp->service?->service_short ?? 'App' }}</x-slot:title>

    @php
        $service = $customerApp->service;
        $billing = $service && $service->service_monthly_charge
            ? '£' . number_format($service->service_monthly_charge, 2) . '/' . strtolower($service->service_payment_frequency ?? 'month')
            : 'Free';
        $severityClasses = [
            'critical' => 'bg-red-100 text-red-700',
            'high' => 'bg-orange-100 text-orange-700',
            'medium' => 'bg-amber-100 text-amber-700',
            'low' => 'bg-blue-100 text-blue-700',
        ];
        $findingStatusClasses = [
            'open' => 'bg-red-100 text-red-700',
            'resolved' => 'bg-green-100 text-green-700',
            'dismissed' => 'bg-gray-100 text-gray-600',
        ];
    @endphp

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold">{{ $service?->service_short ?? 'App' }}</h1>
            @if ($service?->domain_name)
                <p class="text-sm text-gray-500">{{ $service->domain_name }}</p>
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.customer-apps.edit', $customerApp) }}" class="inline-flex items-center px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">Edit</a>
            @if ($service)
                <a href="{{ route('admin.services.show', $service) }}" class="text-sm text-blue-600 hover:underline self-center">&larr; Service</a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main technical detail --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Technical metadata --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Technical Metadata</h2>
                <dl class="grid grid-cols-1 gap-y-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Repository URL</dt>
                        <dd class="mt-1">
                            @if ($customerApp->repo_url)
                                <a href="{{ $customerApp->repo_url }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline break-all font-mono text-xs">{{ $customerApp->repo_url }}</a>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Hosting / Storage Location</dt>
                        <dd class="mt-1">{{ $customerApp->hosting_location ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Codebase Information</dt>
                        <dd class="mt-1 whitespace-pre-wrap">{{ $customerApp->codebase_info ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Packages --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">
                    Installed Packages
                    <span class="ml-1 text-gray-400 font-normal">({{ $customerApp->packages->count() }})</span>
                </h2>
                @if ($customerApp->packages->isEmpty())
                    <p class="text-sm text-gray-400">No packages recorded.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 border-b">
                                    <th class="py-2 pr-4 font-medium">Package</th>
                                    <th class="py-2 pr-4 font-medium">Version</th>
                                    <th class="py-2 font-medium">Last Synced</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($customerApp->packages as $package)
                                    <tr>
                                        <td class="py-2 pr-4 font-medium">{{ $package->name }}</td>
                                        <td class="py-2 pr-4 font-mono text-xs">{{ $package->version ?: '—' }}</td>
                                        <td class="py-2 text-gray-500">{{ $package->synced_at?->format('M j, Y H:i') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Security findings --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">
                    Security Findings
                    <span class="ml-1 text-gray-400 font-normal">({{ $customerApp->findings->count() }})</span>
                </h2>
                @if ($customerApp->findings->isEmpty())
                    <p class="text-sm text-gray-400">No security findings recorded.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 border-b">
                                    <th class="py-2 pr-4 font-medium">Severity</th>
                                    <th class="py-2 pr-4 font-medium">Description</th>
                                    <th class="py-2 pr-4 font-medium">Status</th>
                                    <th class="py-2 font-medium">Last Synced</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($customerApp->findings as $finding)
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $severityClasses[strtolower((string) $finding->severity)] ?? 'bg-gray-100 text-gray-600' }}">
                                                {{ ucfirst((string) $finding->severity) ?: '—' }}
                                            </span>
                                        </td>
                                        <td class="py-2 pr-4">{{ $finding->description }}</td>
                                        <td class="py-2 pr-4">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $findingStatusClasses[strtolower((string) $finding->status)] ?? 'bg-gray-100 text-gray-600' }}">
                                                {{ ucfirst((string) $finding->status) }}
                                            </span>
                                        </td>
                                        <td class="py-2 text-gray-500">{{ $finding->synced_at?->format('M j, Y H:i') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Sidebar: linked service --}}
        <div class="space-y-4">
            <div class="bg-white rounded-lg shadow-sm border p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Linked Service</h2>
                @if ($service)
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500">Name</dt>
                            <dd class="mt-1 font-medium">{{ $service->service_short }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Status</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                    {{ $service->status === 'Active' ? 'bg-green-100 text-green-700' : ($service->status === 'Cancelled' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                    {{ $service->status }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Billing</dt>
                            <dd class="mt-1">{{ $billing }}</dd>
                        </div>
                        @if ($service->stripe_subscription_id)
                            <div>
                                <dt class="text-gray-500">Stripe Subscription</dt>
                                <dd class="mt-1 font-mono text-xs break-all">{{ $service->stripe_subscription_id }}</dd>
                            </div>
                        @endif
                        @if ($service->customer)
                            <div>
                                <dt class="text-gray-500">Customer</dt>
                                <dd class="mt-1">
                                    <a href="{{ route('admin.customers.show', $service->customer) }}" class="text-blue-600 hover:underline">{{ $service->customer->company_name }}</a>
                                </dd>
                            </div>
                        @endif
                    </dl>
                @else
                    <p class="text-sm text-gray-400">No linked service.</p>
                @endif
            </div>

            <div class="bg-white rounded-lg shadow-sm border p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Quick Actions</h2>
                <div class="space-y-2">
                    <a href="{{ route('admin.customer-apps.edit', $customerApp) }}" class="block w-full px-3 py-2 border rounded-md text-sm text-center hover:bg-gray-50">Edit App</a>
                    @if ($service)
                        <a href="{{ route('admin.services.show', $service) }}" class="block w-full px-3 py-2 border rounded-md text-sm text-center hover:bg-gray-50">View Service</a>
                    @endif
                    <form method="POST" action="{{ route('admin.customer-apps.destroy', $customerApp) }}" onsubmit="return confirm('Delete this app? Its packages and findings will also be removed.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="block w-full px-3 py-2 border border-red-200 text-red-600 rounded-md text-sm text-center hover:bg-red-50">Delete App</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
