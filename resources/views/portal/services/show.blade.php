<x-portal-layout>
    <x-slot:title>{{ $service->service_short }}</x-slot:title>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">{{ $service->service_short }}</h1>
            @if ($service->domain_name)
                <p class="text-gray-500 mt-1">
                    <a href="https://{{ $service->domain_name }}" target="_blank" class="text-blue-600 hover:underline">{{ $service->domain_name }}</a>
                </p>
            @endif
        </div>
        <a href="{{ route('portal.services.index') }}" class="text-sm text-blue-600 hover:underline font-medium">&larr; All Services</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- About this service --}}
            @if ($service->service_type === 'Web Hosting')
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-5">
                    <h2 class="font-bold text-blue-900 mb-2">About Your Website Hosting</h2>
                    <p class="text-sm text-blue-800 leading-relaxed">
                        Your website is hosted on our managed servers. This includes your website files, email accounts, databases, and DNS.
                        You can access your hosting control panel (cPanel) and webmail using the quick access buttons.
                    </p>
                </div>
            @elseif ($service->service_short === 'Technical Support Package' || $service->service_type === 'Technical Support')
                <div class="bg-green-50 border border-green-200 rounded-lg p-5">
                    <h2 class="font-bold text-green-900 mb-2">Technical Support Package</h2>
                    <p class="text-sm text-green-800 leading-relaxed mb-4">
                        Your account includes our Technical Support Package. This covers website changes, hardware support,
                        and day-to-day technical assistance. Submit a request below and our team will get back to you.
                    </p>
                    <a href="{{ route('portal.tickets.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-green-700 text-white rounded-md text-sm font-semibold hover:bg-green-800 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Submit Support Request
                    </a>
                </div>
            @endif

            {{-- Service details --}}
            <div class="bg-white rounded-lg border p-6">
                <h2 class="font-bold mb-4">Service Details</h2>
                <p class="text-xs text-gray-400 mb-4">Billing information is synced from Stripe and may take up to 24 hours to reflect recent changes.</p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-gray-500 font-medium">Status</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                {{ $service->status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $service->status }}
                            </span>
                        </dd>
                    </div>

                    @if ($service->service_type)
                        <div>
                            <dt class="text-gray-500 font-medium">Type</dt>
                            <dd class="mt-1 font-medium">{{ $service->service_type }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-gray-500 font-medium">Billing</dt>
                        <dd class="mt-1 font-medium">
                            @if ($service->service_monthly_charge)
                                £{{ number_format($service->service_monthly_charge, 2) }} / {{ $service->service_payment_frequency ?? 'month' }}
                            @else
                                Included
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-gray-500 font-medium">Start Date</dt>
                        <dd class="mt-1">{{ $service->start_date?->format('M j, Y') ?? '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-gray-500 font-medium">Next Payment</dt>
                        <dd class="mt-1">{{ $service->next_payment_date?->format('M j, Y') ?? '—' }}</dd>
                    </div>

                    @if ($managedDomain)
                        <div>
                            <dt class="text-gray-500 font-medium">Domain</dt>
                            <dd class="mt-1">
                                <span class="font-medium">{{ $managedDomain->domain_name }}</span>
                                <span class="text-xs text-gray-400 ml-1">
                                    (expires {{ $managedDomain->expiry_date?->format('M j, Y') }}
                                    @if ($managedDomain->auto_renew) · auto-renew @endif)
                                </span>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Sidebar: Quick Access --}}
        <div class="space-y-4">
            @if ($service->cpanel_username && $service->status === 'Active')
                <div class="bg-white rounded-lg border p-5">
                    <h2 class="font-bold mb-3">Quick Access</h2>
                    <div class="space-y-2">
                        <form method="POST" action="{{ route('portal.services.sso.cpanel', $service) }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 rounded-md border hover:bg-gray-50 transition text-left">
                                <div class="h-9 w-9 rounded-md bg-orange-100 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">cPanel</p>
                                    <p class="text-xs text-gray-500">Files, databases, WordPress</p>
                                </div>
                            </button>
                        </form>

                        <form method="POST" action="{{ route('portal.services.sso.webmail', $service) }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 rounded-md border hover:bg-gray-50 transition text-left">
                                <div class="h-9 w-9 rounded-md bg-blue-100 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Webmail</p>
                                    <p class="text-xs text-gray-500">Access your email</p>
                                </div>
                            </button>
                        </form>

                        @if ($service->domain_name)
                            <a href="https://{{ $service->domain_name }}" target="_blank"
                               class="w-full flex items-center gap-3 px-4 py-3 rounded-md border hover:bg-gray-50 transition">
                                <div class="h-9 w-9 rounded-md bg-green-100 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Visit Website</p>
                                    <p class="text-xs text-gray-500">{{ $service->domain_name }}</p>
                                </div>
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            <div class="bg-white rounded-lg border p-5">
                <h2 class="font-bold mb-3">Need Help?</h2>
                <p class="text-sm text-gray-500 mb-3">Need changes to your website, email setup, or have a technical issue?</p>
                <a href="{{ route('portal.tickets.create') }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition">
                    Open Support Ticket
                </a>
            </div>
        </div>
    </div>
</x-portal-layout>
