<x-portal-layout>
    <x-slot:title>My Services</x-slot:title>

    <h1 class="text-2xl font-semibold mb-4">My Services</h1>

    @if ($services->isEmpty())
        <div class="bg-white rounded-lg shadow-sm border p-8 text-center">
            <p class="text-gray-500">No services configured yet.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach ($services as $service)
                <div class="bg-white rounded-lg shadow-sm border p-5 hover:shadow-md transition">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ $service->service_short }}</h3>
                            @if ($service->domain_name)
                                <p class="text-sm text-gray-500 mt-0.5">{{ $service->domain_name }}</p>
                            @endif
                        </div>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                            {{ $service->status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $service->status }}
                        </span>
                    </div>

                    <div class="flex items-center gap-3 text-sm text-gray-500 mb-4">
                        @if ($service->service_monthly_charge)
                            <span>£{{ number_format($service->service_monthly_charge, 2) }}/{{ strtolower($service->service_payment_frequency ?? 'month') }}</span>
                        @endif
                        @if ($service->service_type)
                            <span class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ $service->service_type }}</span>
                        @endif
                    </div>

                    {{-- Action buttons --}}
                    <div class="flex flex-wrap gap-2 pt-3 border-t">
                        <a href="{{ route('portal.services.show', $service) }}"
                           class="inline-flex items-center gap-1 px-3 py-1.5 border rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                            Details
                        </a>

                        @if ($service->cpanel_username && $service->status === 'Active')
                            <form method="POST" action="{{ route('portal.services.sso.cpanel', $service) }}" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 bg-orange-500 text-white rounded-md text-sm font-medium hover:bg-orange-600 transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/></svg>
                                    cPanel
                                </button>
                            </form>
                            <form method="POST" action="{{ route('portal.services.sso.webmail', $service) }}" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-500 text-white rounded-md text-sm font-medium hover:bg-blue-600 transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    Webmail
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $services->links() }}</div>
    @endif
</x-portal-layout>
