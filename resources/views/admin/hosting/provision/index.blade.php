<x-admin-layout>
    <x-slot:title>Hosting Provisioning Queue</x-slot:title>

    <div class="max-w-7xl">
        <h1 class="text-2xl font-semibold mb-6">Hosting Provisioning Queue</h1>

        <form method="GET" action="{{ route('admin.hosting.provision.index') }}" class="mb-4 flex items-center gap-2">
            <label for="status" class="text-sm text-gray-600">Provisioning status</label>
            <select name="status" id="status" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm">
                <option value="">All</option>
                @foreach (['queued' => 'Queued', 'running' => 'Running', 'succeeded' => 'Succeeded', 'failed' => 'Failed', 'none' => 'Not tracked'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="px-3 py-1.5 bg-gray-100 text-sm rounded-md">Filter</button></noscript>
        </form>

        @if ($services->isEmpty())
            <div class="bg-white rounded-lg shadow-sm border p-8 text-center">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                </svg>
                <p class="text-gray-500 text-sm">No hosting services found.</p>
            </div>
        @else
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domain</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Error</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($services as $service)
                            @php
                                $item = $service->order_item_id ? $orderItems->get($service->order_item_id) : null;
                                $productName = $item ? ($item->product->name ?? $item->product_name) : $service->service_short;
                                $domainName = $service->domainName();
                            @endphp
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $service->customer->company_name ?? 'Unknown' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-700">
                                    {{ $domainName ?? '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ $productName }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <x-status-badge type="provisioning" :status="$service->provisioning_status" />
                                </td>
                                <td class="px-6 py-4 text-xs text-red-600 max-w-xs truncate" title="{{ $service->provisioning_error }}">
                                    {{ $service->provisioning_error }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $service->created_at?->format('d M Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    @if ($service->provisioning_status === 'failed')
                                        <form method="POST" action="{{ route('admin.hosting.provision.retry', $service) }}"
                                              onsubmit="return confirm('Retry provisioning for {{ $domainName }}?')">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-600 text-white text-sm font-medium rounded-md hover:bg-amber-700 transition">Retry</button>
                                        </form>
                                    @elseif (in_array($service->provisioning_status, ['queued', 'running'], true))
                                        <span class="text-xs text-gray-400">In progress</span>
                                    @elseif ($service->provisioning_status === null && $service->status === 'Pending')
                                    <form method="POST" action="{{ route('admin.hosting.provision.provision', $service) }}"
                                          onsubmit="return confirm('Provision hosting account for {{ $domainName }}?')">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            Provision
                                        </button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $services->links() }}</div>
        @endif
    </div>
</x-admin-layout>
