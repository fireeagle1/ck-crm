<x-portal-layout>
    <x-slot:title>{{ $service->service_short }}</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ $service->service_short }}</h1>
            @if ($service->domain_name)
                <p class="text-sm text-gray-500 mt-0.5">{{ $service->domain_name }}</p>
            @endif
        </div>
        <a href="{{ route('portal.services.index') }}" class="text-sm text-blue-600 hover:underline">&larr; All Services</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main details --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Status</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                {{ $service->status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $service->status }}
                            </span>
                        </dd>
                    </div>

                    @if ($service->service_type)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Service Type</dt>
                            <dd class="mt-1 text-sm text-gray-800">{{ $service->service_type }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Billing</dt>
                        <dd class="mt-1 text-sm text-gray-800">
                            @if ($service->service_monthly_charge)
                                £{{ number_format($service->service_monthly_charge, 2) }} / {{ $service->service_payment_frequency ?? 'month' }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Start Date</dt>
                        <dd class="mt-1 text-sm text-gray-800">{{ $service->start_date?->format('M j, Y') ?? '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Next Payment</dt>
                        <dd class="mt-1 text-sm text-gray-800">{{ $service->next_payment_date?->format('M j, Y') ?? '—' }}</dd>
                    </div>

                    @if ($service->end_date)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500 font-semibold">End Date</dt>
                            <dd class="mt-1 text-sm text-gray-800">{{ $service->end_date->format('M j, Y') }}</dd>
                        </div>
                    @endif

                    @if ($service->domain_name)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Domain</dt>
                            <dd class="mt-1 text-sm text-gray-800">
                                <a href="https://{{ $service->domain_name }}" target="_blank" class="text-blue-600 hover:underline">
                                    {{ $service->domain_name }}
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Quick Actions sidebar --}}
        <div class="space-y-4">
            @if ($service->cpanel_username && $service->status === 'Active')
                <div class="bg-white rounded-lg shadow-sm border p-5">
                    <h2 class="text-sm font-semibold text-gray-700 mb-3">Quick Access</h2>
                    <div class="space-y-2">
                        <form method="POST" action="{{ route('portal.services.sso.cpanel', $service) }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 rounded-md border hover:bg-gray-50 transition text-left">
                                <div class="h-9 w-9 rounded-md bg-orange-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">cPanel</p>
                                    <p class="text-xs text-gray-500">File manager, databases, DNS</p>
                                </div>
                            </button>
                        </form>

                        <form method="POST" action="{{ route('portal.services.sso.webmail', $service) }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 rounded-md border hover:bg-gray-50 transition text-left">
                                <div class="h-9 w-9 rounded-md bg-blue-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Webmail</p>
                                    <p class="text-xs text-gray-500">Access your email inbox</p>
                                </div>
                            </button>
                        </form>

                        @if ($service->domain_name)
                            <a href="https://{{ $service->domain_name }}" target="_blank"
                               class="w-full flex items-center gap-3 px-4 py-3 rounded-md border hover:bg-gray-50 transition">
                                <div class="h-9 w-9 rounded-md bg-green-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Visit Website</p>
                                    <p class="text-xs text-gray-500">{{ $service->domain_name }}</p>
                                </div>
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm border p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Need Help?</h2>
                <a href="{{ route('portal.tickets.create') }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    Open Support Ticket
                </a>
            </div>
        </div>
    </div>
</x-portal-layout>
