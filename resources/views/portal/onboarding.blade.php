<x-portal-layout>
    <x-slot:title>Complete Your Account</x-slot:title>

    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold">Welcome! Let's set up your account</h1>
            <p class="text-sm text-gray-500 mt-1">We need a few details about your company to get started.</p>
        </div>

        <div class="bg-white rounded-lg shadow-sm border p-6">
            <form method="POST" action="{{ route('portal.onboarding.update') }}">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    <div>
                        <label for="company_name" class="block text-sm font-medium text-gray-700">Company Name <span class="text-red-500">*</span></label>
                        <input type="text" name="company_name" id="company_name" required
                               value="{{ old('company_name', $customer?->company_name) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('company_name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="customer_name" class="block text-sm font-medium text-gray-700">Primary Contact Name</label>
                        <input type="text" name="customer_name" id="customer_name"
                               value="{{ old('customer_name', $customer?->customer_name ?? auth()->user()->full_name) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label for="phone_number" class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <input type="text" name="phone_number" id="phone_number"
                               value="{{ old('phone_number', $customer?->phone_number) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label for="address_line1" class="block text-sm font-medium text-gray-700">Address Line 1 <span class="text-red-500">*</span></label>
                        <input type="text" name="address_line1" id="address_line1" required
                               value="{{ old('address_line1', $customer?->address_line1) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('address_line1') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="address_line2" class="block text-sm font-medium text-gray-700">Address Line 2</label>
                        <input type="text" name="address_line2" id="address_line2"
                               value="{{ old('address_line2', $customer?->address_line2) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700">City <span class="text-red-500">*</span></label>
                            <input type="text" name="city" id="city" required
                                   value="{{ old('city', $customer?->city) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('city') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="state" class="block text-sm font-medium text-gray-700">County</label>
                            <input type="text" name="state" id="state"
                                   value="{{ old('state', $customer?->state) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="postal_code" class="block text-sm font-medium text-gray-700">Postcode <span class="text-red-500">*</span></label>
                            <input type="text" name="postal_code" id="postal_code" required
                                   value="{{ old('postal_code', $customer?->postal_code) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('postal_code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="country" class="block text-sm font-medium text-gray-700">Country</label>
                            <input type="text" name="country" id="country"
                                   value="{{ old('country', $customer?->country ?? 'United Kingdom') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <button type="submit" class="w-full px-4 py-2.5 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 mt-2">
                        Complete Setup
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-portal-layout>
