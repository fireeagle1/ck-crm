<x-admin-layout>
    <x-slot:title>Add Web App</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold">Add Web App</h1>
            <p class="text-sm text-gray-500">Pick the service this app belongs to, then fill in its details.</p>
        </div>
        <a href="{{ route('admin.customer-apps.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Web Apps</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden max-w-2xl">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Service</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Customer</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($services as $service)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ $service->service_short }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $service->customer?->company_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.customer-apps.create', $service) }}" class="text-blue-600 hover:underline font-medium">Add app &rarr;</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-gray-500">
                            Every service already has a web app linked. Create a service first to add a new one.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
