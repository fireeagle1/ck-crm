<x-admin-layout>
    <x-slot:title>Add Asset</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Add Asset</h1>
        <a href="{{ route('admin.assets.index') }}" class="text-sm text-blue-600 hover:underline">&larr; CMDB</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.assets.store') }}">
            @csrf

            <div class="space-y-4">
                <div>
                    <label for="customer_id" class="block text-sm font-medium text-gray-700">Customer <span class="text-red-500">*</span></label>
                    <select name="customer_id" id="customer_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select customer...</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->company_id }}" {{ old('customer_id') == $customer->company_id ? 'selected' : '' }}>
                                {{ $customer->company_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('customer_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="device_name" class="block text-sm font-medium text-gray-700">Device Name <span class="text-red-500">*</span></label>
                    <input type="text" name="device_name" id="device_name" required value="{{ old('device_name') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="e.g. DELL-SRV01">
                    @error('device_name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="device_type" class="block text-sm font-medium text-gray-700">Device Type</label>
                        <input type="text" name="device_type" id="device_type" value="{{ old('device_type') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g. Server, Laptop, Switch">
                    </div>
                    <div>
                        <label for="serial_number" class="block text-sm font-medium text-gray-700">Serial Number</label>
                        <input type="text" name="serial_number" id="serial_number" value="{{ old('serial_number') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                        <input type="text" name="location" id="location" value="{{ old('location') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g. Server Room A">
                    </div>
                    <div>
                        <label for="asset_status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="asset_status" id="asset_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="Active" selected>Active</option>
                            <option value="In Repair">In Repair</option>
                            <option value="Decommissioned">Decommissioned</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea name="notes" id="notes" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('notes') }}</textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                        Create Asset
                    </button>
                    <a href="{{ route('admin.assets.index') }}" class="px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
