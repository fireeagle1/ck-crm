<x-admin-layout>
    <x-slot:title>Edit {{ $domain->domain_name }}</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Edit Domain</h1>
        <a href="{{ route('admin.domains.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Domains</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.domains.update', $domain) }}">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <label for="domain_name" class="block text-sm font-medium text-gray-700">Domain Name <span class="text-red-500">*</span></label>
                    <input type="text" name="domain_name" id="domain_name" required
                           value="{{ old('domain_name', $domain->domain_name) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('domain_name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="company_id" class="block text-sm font-medium text-gray-700">Customer</label>
                        <select name="company_id" id="company_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Unassigned</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->company_id }}" {{ old('company_id', $domain->company_id) == $customer->company_id ? 'selected' : '' }}>
                                    {{ $customer->company_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="registrar" class="block text-sm font-medium text-gray-700">Registrar</label>
                        <input type="text" name="registrar" id="registrar"
                               value="{{ old('registrar', $domain->registrar) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label for="registration_date" class="block text-sm font-medium text-gray-700">Registered</label>
                        <input type="date" name="registration_date" id="registration_date"
                               value="{{ old('registration_date', $domain->registration_date?->format('Y-m-d')) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="expiry_date" class="block text-sm font-medium text-gray-700">Expires</label>
                        <input type="date" name="expiry_date" id="expiry_date"
                               value="{{ old('expiry_date', $domain->expiry_date?->format('Y-m-d')) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="cost" class="block text-sm font-medium text-gray-700">Annual Cost (£)</label>
                        <input type="number" step="0.01" min="0" name="cost" id="cost"
                               value="{{ old('cost', $domain->cost) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div>
                    <label for="domain_admin_notes" class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea name="domain_admin_notes" id="domain_admin_notes" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('domain_admin_notes', $domain->domain_admin_notes) }}</textarea>
                </div>

                <div>
                    <label for="stripe_subscription_id" class="block text-sm font-medium text-gray-700">Stripe Subscription ID</label>
                    <input type="text" name="stripe_subscription_id" id="stripe_subscription_id"
                           value="{{ old('stripe_subscription_id', $domain->stripe_subscription_id) }}"
                           placeholder="sub_..."
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 font-mono text-sm">
                    <p class="text-xs text-gray-400 mt-1">If domain renewal is billed via a Stripe subscription, enter the ID here.</p>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                        Save Changes
                    </button>
                    <a href="{{ route('admin.domains.index') }}" class="px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
