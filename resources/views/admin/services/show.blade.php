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

    @if ($service->billing_state === 'billing_failed')
        <div class="mb-6 rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700" role="alert" data-billing-warning>
            <p class="font-semibold">Billing setup failed for this service.</p>
            @if ($service->billing_error)
                <p class="mt-1">{{ $service->billing_error }}</p>
            @endif
            <form method="POST" action="{{ route('admin.services.retry-billing', $service) }}" class="mt-3 flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label for="retry_stripe_price_id" class="block text-xs text-gray-600">Stripe price ID</label>
                    <input id="retry_stripe_price_id" name="stripe_price_id" type="text" required
                           value="{{ old('stripe_price_id') }}" placeholder="price_..."
                           class="mt-1 rounded-md border-gray-300 text-sm">
                    @error('stripe_price_id') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="retry_contact_email" class="block text-xs text-gray-600">Contact email</label>
                    <input id="retry_contact_email" name="contact_email" type="email" required
                           value="{{ old('contact_email', $service->customer?->users->first()?->email) }}"
                           class="mt-1 rounded-md border-gray-300 text-sm">
                    @error('contact_email') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded-md bg-red-600 text-white text-sm font-medium hover:bg-red-700">Retry billing</button>
            </form>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main details --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Service Details</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Status</dt>
                        <dd class="mt-1">
                            <x-status-badge type="service" :status="$service->status" size="px-2.5 py-0.5" />
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
                    <div>
                        <dt class="text-gray-500">Provisioning</dt>
                        <dd class="mt-1">
                            <x-status-badge type="provisioning" :status="$service->provisioning_status" size="px-2.5 py-0.5" />
                            @if ($service->provisioning_status === 'failed' && $service->provisioning_error)
                                <p class="mt-2 text-xs text-red-700" data-provisioning-error>{{ $service->provisioning_error }}</p>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Web App</dt>
                        <dd class="mt-1">
                            @if ($service->customerApp)
                                <a href="{{ route('admin.customer-apps.show', $service->customerApp) }}" class="text-blue-600 hover:underline">{{ $service->customerApp->display_name }}</a>
                            @else
                                <a href="{{ route('admin.customer-apps.create', $service) }}" class="text-blue-600 hover:underline">Create web app</a>
                            @endif
                        </dd>
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

            {{-- WHM Server Info (on demand only; the page never calls WHM) --}}
            @if ($service->cpanel_username)
                @php($whmInfo = session('whmInfo'))
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold text-gray-700">Server Resources (WHM)</h2>
                        <form method="POST" action="{{ route('admin.services.refresh-whm', $service) }}">
                            @csrf
                            <button type="submit" class="px-3 py-1 border rounded-md text-xs hover:bg-gray-50">Refresh from WHM</button>
                        </form>
                    </div>
                    @if (session('whmError'))
                        <p class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">{{ session('whmError') }}</p>
                    @elseif (is_array($whmInfo))
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                            <div>
                                <dt class="text-gray-500">Domain</dt>
                                <dd class="mt-1 font-medium">{{ $whmInfo['domain'] ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Package</dt>
                                <dd class="mt-1">{{ $whmInfo['plan'] ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Status</dt>
                                <dd class="mt-1">
                                    @if (! empty($whmInfo['suspended']))
                                        <x-status-badge type="service" status="Suspended" />
                                    @else
                                        <x-status-badge type="service" status="Active" />
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    @else
                        <p class="text-sm text-gray-500">Not loaded. Use refresh to query WHM.</p>
                    @endif
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
