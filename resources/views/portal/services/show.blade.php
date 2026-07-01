<x-portal-layout>
    <x-slot:title>{{ $service->service_short }}</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">{{ $service->service_short }}</h1>
        <a href="{{ route('portal.services.index') }}" class="text-sm text-blue-600 hover:underline">&larr; All Services</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-3xl">
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

            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Billing Cycle</dt>
                <dd class="mt-1 text-sm text-gray-800">{{ $service->service_payment_frequency ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Monthly Charge</dt>
                <dd class="mt-1 text-sm text-gray-800">
                    {{ $service->service_monthly_charge ? '£' . number_format($service->service_monthly_charge, 2) : '—' }}
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
        </dl>
    </div>
</x-portal-layout>
