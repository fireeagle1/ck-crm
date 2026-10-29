<x-admin-layout>
    <x-slot:title>Web Apps</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Web Apps</h1>
        <a href="{{ route('admin.customer-apps.pick') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
            + Add Web App
        </a>
    </div>

    <form method="GET" action="{{ route('admin.customer-apps.index') }}" class="mb-4">
        <input type="text" name="q" value="{{ $search }}"
               placeholder="Search by repo, hosting, service or customer..."
               class="w-full max-w-md rounded-md border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
    </form>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Service</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Customer</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Repository</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Hosting</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Packages</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Findings</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($customerApps as $app)
                    <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('admin.customer-apps.show', $app) }}'">
                        <td class="px-4 py-3 font-medium text-blue-600">{{ $app->service?->service_short ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $app->service?->customer?->company_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500 max-w-xs truncate">{{ $app->repo_url ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $app->hosting_location ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $app->packages_count }}</td>
                        <td class="px-4 py-3">
                            @if ($app->open_findings_count > 0)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700">
                                    {{ $app->open_findings_count }} open
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">
                                    {{ $app->findings_count }} resolved
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No web apps yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $customerApps->links() }}</div>
</x-admin-layout>
