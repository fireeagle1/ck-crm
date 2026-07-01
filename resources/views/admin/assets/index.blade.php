<x-admin-layout>
    <x-slot:title>CMDB</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">CMDB — Assets</h1>
        <a href="{{ route('admin.assets.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
            Add Asset
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Device</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Customer</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Type</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Location</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($assets as $asset)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('admin.assets.show', $asset) }}" class="text-blue-600 hover:underline">
                                {{ $asset->device_name }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $asset->customer?->company_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $asset->device_type ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $asset->location ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $asset->asset_status === 'Active' ? 'bg-green-100 text-green-700' : ($asset->asset_status === 'Decommissioned' ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ $asset->asset_status ?? 'Active' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.assets.edit', $asset) }}" class="text-blue-600 hover:underline text-sm">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No assets.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $assets->links() }}</div>
</x-admin-layout>
