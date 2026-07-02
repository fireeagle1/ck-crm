<x-portal-layout>
    <x-slot:title>My Services</x-slot:title>

    <h1 class="text-3xl font-bold tracking-tight mb-2">My Services</h1>
    <p class="text-gray-500 mb-6">Your active services managed by {{ \App\Models\Setting::get('site_name', 'CK Enterprises') }}.</p>

    {{-- Support plan banner --}}
    @if ($hasSupportPlan)
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 flex items-center justify-between">
            <div>
                <p class="font-semibold text-green-900">Technical Support Package</p>
                <p class="text-sm text-green-700">Your account includes technical support. Need help? Raise a request.</p>
            </div>
            <a href="{{ route('portal.tickets.create') }}" class="px-4 py-2 bg-green-700 text-white rounded-md text-sm font-semibold hover:bg-green-800 transition shrink-0">
                Raise Request
            </a>
        </div>
    @endif

    @if ($services->isEmpty())
        <div class="bg-white rounded-lg border p-8 text-center">
            <p class="text-gray-500">No active services.</p>
        </div>
    @else
        <div class="bg-white rounded-lg border overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Service</th>
                        <th class="px-5 py-3 text-left font-semibold text-gray-600">URL</th>
                        <th class="px-5 py-3 text-center font-semibold text-gray-600">Domain with us</th>
                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Billing</th>
                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($services as $service)
                        @php
                            $isTechSupport = $service->service_type === 'Technical Support';
                            $matchedDomain = !$isTechSupport && $service->domain_name ? $domains->firstWhere('domain_name', strtolower($service->domain_name)) : null;
                        @endphp
                        <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('portal.services.show', $service) }}'">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-blue-600">{{ $service->service_short }}</p>
                                @if ($service->service_type)
                                    <p class="text-xs text-gray-400">{{ $service->service_type }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if (!$isTechSupport && $service->domain_name)
                                    <span class="text-sm text-gray-700">{{ $service->domain_name }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center">
                                @if ($isTechSupport)
                                    <span class="text-gray-300">—</span>
                                @elseif ($matchedDomain)
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-green-100">
                                        <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </span>
                                @else
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100">
                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-gray-600">
                                @if ($service->service_monthly_charge)
                                    £{{ number_format($service->service_monthly_charge, 2) }}/{{ strtolower($service->service_payment_frequency ?? 'month') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                    {{ $service->status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $service->status }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $services->links() }}</div>
    @endif
</x-portal-layout>
