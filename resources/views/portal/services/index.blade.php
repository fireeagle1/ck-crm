<x-portal-layout>
    <x-slot:title>My Websites</x-slot:title>

    <h1 class="text-2xl font-semibold mb-4">My Websites</h1>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Service</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Billing Cycle</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($services as $service)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">{{ $service->service_short }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $service->status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $service->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $service->service_payment_frequency ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('portal.services.show', $service) }}" class="text-blue-600 hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">No services.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $services->links() }}</div>
</x-portal-layout>
