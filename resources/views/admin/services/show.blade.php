<x-admin-layout>
    <x-slot:title>{{ $service->service_short }}</x-slot:title>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold">{{ $service->service_short }}</h1>
            @if ($service->domain_name)
                <p class="text-sm text-gray-500">{{ $service->domain_name }}</p>
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.services.edit', $service) }}" class="inline-flex items-center px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">Edit</a>
            <a href="{{ route('admin.services.index') }}" class="text-sm text-blue-600 hover:underline self-center">&larr; Services</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main details --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Service Details</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
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
                        <dt class="text-gray-500">Customer</dt>
                        <dd class="mt-1">
                            <a href="{{ route('admin.customers.show', $service->customer) }}" class="text-blue-600 hover:underline">{{ $service->customer?->company_name }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Type</dt>
                        <dd class="mt-1">{{ $service->service_type ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Billing</dt>
                        <dd class="mt-1">{{ $service->service_monthly_charge ? '£' . number_format($service->service_monthly_charge, 2) . '/' . strtolower($service->service_payment_frequency ?? 'month') : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Start Date</dt>
                        <dd class="mt-1">{{ $service->start_date?->format('M j, Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Next Payment</dt>
                        <dd class="mt-1">{{ $service->next_payment_date?->format('M j, Y') ?? '—' }}</dd>
                    </div>
                    @if ($service->cpanel_username)
                        <div>
                            <dt class="text-gray-500">cPanel Username</dt>
                            <dd class="mt-1 font-mono text-xs">{{ $service->cpanel_username }}</dd>
                        </div>
                    @endif
                    @if ($service->stripe_subscription_id)
                        <div>
                            <dt class="text-gray-500">Stripe Subscription</dt>
                            <dd class="mt-1 font-mono text-xs">{{ $service->stripe_subscription_id }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- WHM Server Info --}}
            @if ($whmInfo)
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h2 class="text-sm font-semibold text-gray-700 mb-4">Server Resources (WHM)</h2>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        <div>
                            <dt class="text-gray-500">Domain</dt>
                            <dd class="mt-1 font-medium">{{ $whmInfo['domain'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Package</dt>
                            <dd class="mt-1">{{ $whmInfo['plan'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Disk Usage</dt>
                            <dd class="mt-1">
                                {{ $whmInfo['disk_used'] ?? '?' }} / {{ $whmInfo['disk_limit'] ?? 'unlimited' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">IP Address</dt>
                            <dd class="mt-1 font-mono text-xs">{{ $whmInfo['ip'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Created</dt>
                            <dd class="mt-1">{{ $whmInfo['start_date'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Status</dt>
                            <dd class="mt-1">
                                @if ($whmInfo['suspended'])
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700">Suspended</span>
                                @else
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Active</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            <div class="bg-white rounded-lg shadow-sm border p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Quick Actions</h2>
                <div class="space-y-2">
                    <a href="{{ route('admin.services.edit', $service) }}" class="block w-full px-3 py-2 border rounded-md text-sm text-center hover:bg-gray-50">Edit Service</a>
                    @if ($service->customer)
                        <a href="{{ route('admin.customers.show', $service->customer) }}" class="block w-full px-3 py-2 border rounded-md text-sm text-center hover:bg-gray-50">View Customer</a>
                    @endif
                    <form method="POST" action="{{ route('admin.services.destroy', $service) }}" onsubmit="return confirm('Delete this service?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="block w-full px-3 py-2 border border-red-200 text-red-600 rounded-md text-sm text-center hover:bg-red-50">Delete Service</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
