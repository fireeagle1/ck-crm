<x-admin-layout>
    <x-slot:title>Add Service</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Add Service</h1>
        <a href="{{ route('admin.services.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Services</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.services.store') }}">
            @csrf

            <div class="space-y-4">
                <div>
                    <label for="company_id" class="block text-sm font-medium text-gray-700">Customer <span class="text-red-500">*</span></label>
                    <select name="company_id" id="company_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select customer...</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->company_id }}" {{ old('company_id') == $customer->company_id ? 'selected' : '' }}>
                                {{ $customer->company_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('company_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="service_short" class="block text-sm font-medium text-gray-700">Service Name <span class="text-red-500">*</span></label>
                    <input type="text" name="service_short" id="service_short" required value="{{ old('service_short') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="e.g. Website Hosting – example.co.uk">
                    @error('service_short') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="Active" selected>Active</option>
                        <option value="Suspended">Suspended</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="service_monthly_charge" class="block text-sm font-medium text-gray-700">Monthly Charge (£)</label>
                        <input type="number" step="0.01" min="0" name="service_monthly_charge" id="service_monthly_charge"
                               value="{{ old('service_monthly_charge') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="service_payment_frequency" class="block text-sm font-medium text-gray-700">Payment Frequency</label>
                        <select name="service_payment_frequency" id="service_payment_frequency" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">—</option>
                            <option value="Monthly" {{ old('service_payment_frequency') === 'Monthly' ? 'selected' : '' }}>Monthly</option>
                            <option value="Quarterly" {{ old('service_payment_frequency') === 'Quarterly' ? 'selected' : '' }}>Quarterly</option>
                            <option value="Annually" {{ old('service_payment_frequency') === 'Annually' ? 'selected' : '' }}>Annually</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                        <input type="date" name="start_date" id="start_date" value="{{ old('start_date') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                        <input type="date" name="end_date" id="end_date" value="{{ old('end_date') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                        Create Service
                    </button>
                    <a href="{{ route('admin.services.index') }}" class="px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
