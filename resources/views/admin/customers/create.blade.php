<x-admin-layout>
    <x-slot:title>Add Customer</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Add Customer</h1>
        <a href="{{ route('admin.customers.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Customers</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.customers.store') }}">
            @csrf

            <div class="space-y-4">
                <div>
                    <label for="company_name" class="block text-sm font-medium text-gray-700">Company Name <span class="text-red-500">*</span></label>
                    <input type="text" name="company_name" id="company_name" required value="{{ old('company_name') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('company_name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="customer_name" class="block text-sm font-medium text-gray-700">Contact Name</label>
                    <input type="text" name="customer_name" id="customer_name" value="{{ old('customer_name') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="phone_number" class="block text-sm font-medium text-gray-700">Phone</label>
                        <input type="text" name="phone_number" id="phone_number" value="{{ old('phone_number') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div>
                    <label for="address_line1" class="block text-sm font-medium text-gray-700">Address Line 1</label>
                    <input type="text" name="address_line1" id="address_line1" value="{{ old('address_line1') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label for="address_line2" class="block text-sm font-medium text-gray-700">Address Line 2</label>
                    <input type="text" name="address_line2" id="address_line2" value="{{ old('address_line2') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="city" class="block text-sm font-medium text-gray-700">City</label>
                        <input type="text" name="city" id="city" value="{{ old('city') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="state" class="block text-sm font-medium text-gray-700">County</label>
                        <input type="text" name="state" id="state" value="{{ old('state') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="postal_code" class="block text-sm font-medium text-gray-700">Postcode</label>
                        <input type="text" name="postal_code" id="postal_code" value="{{ old('postal_code') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="country" class="block text-sm font-medium text-gray-700">Country</label>
                        <input type="text" name="country" id="country" value="{{ old('country', 'United Kingdom') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                        Create Customer
                    </button>
                    <a href="{{ route('admin.customers.index') }}" class="px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
