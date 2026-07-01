<x-admin-layout>
    <x-slot:title>Edit Service</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Edit Service</h1>
        <a href="{{ route('admin.services.show', $service) }}" class="text-sm text-blue-600 hover:underline">&larr; Back</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.services.update', $service) }}">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="company_id" class="block text-sm font-medium text-gray-700">Customer</label>
                        <select name="company_id" id="company_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->company_id }}" {{ old('company_id', $service->company_id) == $customer->company_id ? 'selected' : '' }}>
                                    {{ $customer->company_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="service_type" class="block text-sm font-medium text-gray-700">Service Type</label>
                        <select name="service_type" id="service_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">—</option>
                            @foreach (['Technical Support', 'Web Hosting', 'Domain Registration', 'Other'] as $type)
                                <option value="{{ $type }}" {{ old('service_type', $service->service_type) === $type ? 'selected' : '' }}>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label for="service_short" class="block text-sm font-medium text-gray-700">Service Name</label>
                    <input type="text" name="service_short" id="service_short" required
                           value="{{ old('service_short', $service->service_short) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="domain_name" class="block text-sm font-medium text-gray-700">Domain Name</label>
                        <input type="text" name="domain_name" id="domain_name"
                               value="{{ old('domain_name', $service->domain_name) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="example.co.uk">
                    </div>
                    <div>
                        <label for="cpanel_username" class="block text-sm font-medium text-gray-700">cPanel Username</label>
                        <input type="text" name="cpanel_username" id="cpanel_username"
                               value="{{ old('cpanel_username', $service->cpanel_username) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @foreach (['Active', 'Suspended', 'Cancelled'] as $s)
                                <option value="{{ $s }}" {{ old('status', $service->status) === $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="service_monthly_charge" class="block text-sm font-medium text-gray-700">Monthly (£)</label>
                        <input type="number" step="0.01" name="service_monthly_charge" id="service_monthly_charge"
                               value="{{ old('service_monthly_charge', $service->service_monthly_charge) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="service_payment_frequency" class="block text-sm font-medium text-gray-700">Frequency</label>
                        <select name="service_payment_frequency" id="service_payment_frequency" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">—</option>
                            @foreach (['Monthly', 'Quarterly', 'Annually'] as $f)
                                <option value="{{ $f }}" {{ old('service_payment_frequency', $service->service_payment_frequency) === $f ? 'selected' : '' }}>{{ $f }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                        <input type="date" name="start_date" id="start_date"
                               value="{{ old('start_date', $service->start_date?->format('Y-m-d')) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                        <input type="date" name="end_date" id="end_date"
                               value="{{ old('end_date', $service->end_date?->format('Y-m-d')) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">Save Changes</button>
                    <a href="{{ route('admin.services.show', $service) }}" class="px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
