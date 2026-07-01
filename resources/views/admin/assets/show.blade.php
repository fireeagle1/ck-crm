<x-admin-layout>
    <x-slot:title>{{ $asset->device_name }}</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">{{ $asset->device_name }}</h1>
        <div class="flex gap-2">
            <a href="{{ route('admin.assets.edit', $asset) }}" class="inline-flex items-center px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">
                Edit
            </a>
            <a href="{{ route('admin.assets.index') }}" class="text-sm text-blue-600 hover:underline self-center">&larr; CMDB</a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-3xl">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Customer</dt>
                <dd class="mt-1 text-sm">
                    @if ($asset->customer)
                        <a href="{{ route('admin.customers.show', $asset->customer) }}" class="text-blue-600 hover:underline">
                            {{ $asset->customer->company_name }}
                        </a>
                    @else
                        —
                    @endif
                </dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Status</dt>
                <dd class="mt-1">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                        {{ $asset->asset_status === 'Active' ? 'bg-green-100 text-green-700' : ($asset->asset_status === 'Decommissioned' ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-700') }}">
                        {{ $asset->asset_status ?? 'Active' }}
                    </span>
                </dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Device Type</dt>
                <dd class="mt-1 text-sm text-gray-800">{{ $asset->device_type ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Serial Number</dt>
                <dd class="mt-1 text-sm font-mono text-gray-800">{{ $asset->serial_number ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Location</dt>
                <dd class="mt-1 text-sm text-gray-800">{{ $asset->location ?? '—' }}</dd>
            </div>
        </dl>

        @if ($asset->notes)
            <div class="mt-6 pt-4 border-t">
                <h2 class="text-xs uppercase tracking-wide text-gray-500 font-semibold mb-2">Notes</h2>
                <div class="prose prose-sm max-w-none text-gray-700">
                    {!! nl2br(e($asset->notes)) !!}
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
