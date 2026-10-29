<x-admin-layout>
    <x-slot:title>{{ $customerApp->service?->service_short }} — App</x-slot:title>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold">{{ $customerApp->service?->service_short }}</h1>
            <p class="text-sm text-gray-500">Technical record</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.customer-apps.edit', $customerApp) }}" class="inline-flex items-center px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">Edit</a>
            @if ($customerApp->service)
                <a href="{{ route('admin.services.show', $customerApp->service) }}" class="text-sm text-blue-600 hover:underline self-center">&larr; Service</a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            {{-- Technical metadata --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Technical Details</h2>
                <dl class="grid grid-cols-1 gap-y-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Display Name <span class="text-gray-400 font-normal">(shown to customer)</span></dt>
                        <dd class="mt-1 font-medium">{{ $customerApp->display_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">App / Installation URL <span class="text-gray-400 font-normal">(shown to customer)</span></dt>
                        <dd class="mt-1">
                            @if ($customerApp->install_url)
                                <a href="{{ $customerApp->install_url }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline break-all">{{ $customerApp->install_url }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Repository URL</dt>
                        <dd class="mt-1 font-mono text-xs break-all">{{ $customerApp->repo_url ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Hosting / Storage Location</dt>
                        <dd class="mt-1">{{ $customerApp->hosting_location ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Codebase Information</dt>
                        <dd class="mt-1 whitespace-pre-line">{{ $customerApp->codebase_info ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Installed packages --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Installed Packages ({{ $customerApp->packages->count() }})</h2>
                @if ($customerApp->packages->isEmpty())
                    <p class="text-sm text-gray-400">No packages recorded.</p>
                @else
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-1 pr-4">Package</th>
                                <th class="py-1 pr-4">Version</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customerApp->packages as $package)
                                <tr class="border-t">
                                    <td class="py-1 pr-4 font-mono text-xs">{{ $package->name }}</td>
                                    <td class="py-1 pr-4 font-mono text-xs">{{ $package->version }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Security findings --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Security Findings ({{ $customerApp->findings->count() }})</h2>
                @if ($customerApp->findings->isEmpty())
                    <p class="text-sm text-gray-400">No findings recorded.</p>
                @else
                    <ul class="space-y-3">
                        @foreach ($customerApp->findings as $finding)
                            <li class="border rounded-md p-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold uppercase text-gray-600">{{ $finding->severity }}</span>
                                    <span class="text-xs rounded-full px-2 py-0.5 bg-gray-100 text-gray-700">{{ $finding->status }}</span>
                                </div>
                                <p class="mt-1 text-sm">{{ $finding->description }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- Linked service (name / status / billing) --}}
        <div class="space-y-4">
            <div class="bg-white rounded-lg shadow-sm border p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Linked Service</h2>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-gray-500">Name</dt>
                        <dd class="mt-1 font-medium">{{ $customerApp->service?->service_short ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Status</dt>
                        <dd class="mt-1">{{ $customerApp->service?->status ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Billing</dt>
                        <dd class="mt-1">{{ $customerApp->service?->service_monthly_charge ? '£' . number_format($customerApp->service->service_monthly_charge, 2) . '/' . strtolower($customerApp->service->service_payment_frequency ?? 'month') : 'Free' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</x-admin-layout>
