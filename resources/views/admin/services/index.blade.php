<x-admin-layout>
    <x-slot:title>Services</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Services</h1>
        <a href="{{ route('admin.services.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
            + Add Service
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Service</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Customer</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Monthly</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Frequency</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Billing</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Start</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($services as $service)
                    <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('admin.services.show', $service) }}'">
                        <td class="px-4 py-3 font-medium text-blue-600">{{ $service->service_short }}</td>
                        <td class="px-4 py-3">
                            {{ $service->customer?->company_name ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $service->status === 'Active' ? 'bg-green-100 text-green-700' : ($service->status === 'Cancelled' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ $service->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ $service->service_monthly_charge ? '£' . number_format($service->service_monthly_charge, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $service->service_payment_frequency ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($service->stripe_subscription_id)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Linked</span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600">Manual</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $service->start_date?->format('Y-m-d') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">No services.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $services->links() }}</div>
</x-admin-layout>
