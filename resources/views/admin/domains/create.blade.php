<x-admin-layout>
    <x-slot:title>Add Domain</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Add Domain</h1>
        <a href="{{ route('admin.domains.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Domains</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.domains.store') }}">
            @csrf
            <div class="space-y-4">
                <div>
                    <label for="domain_name" class="block text-sm font-medium text-gray-700">Domain Name <span class="text-red-500">*</span></label>
                    <input type="text" name="domain_name" id="domain_name" required value="{{ old('domain_name') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="example.co.uk">
                    @error('domain_name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    @if (session('trashed_domain_id'))
                        <button type="submit" form="restore-domain-form" class="mt-2 px-3 py-1.5 border rounded-md text-sm font-medium hover:bg-gray-50">Restore deleted domain</button>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-customer-picker :selected="$selectedCustomer" />
                    </div>
                    <div>
                        <label for="registrar" class="block text-sm font-medium text-gray-700">Registrar</label>
                        <input type="text" name="registrar" id="registrar" value="{{ old('registrar') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g. eNom, Namecheap">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label for="registration_date" class="block text-sm font-medium text-gray-700">Registered</label>
                        <input type="date" name="registration_date" id="registration_date" value="{{ old('registration_date') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="expiry_date" class="block text-sm font-medium text-gray-700">Expires</label>
                        <input type="date" name="expiry_date" id="expiry_date" value="{{ old('expiry_date') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="cost" class="block text-sm font-medium text-gray-700">Annual Cost (£)</label>
                        <input type="number" step="0.01" min="0" name="cost" id="cost" value="{{ old('cost') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div>
                    <label for="domain_admin_notes" class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea name="domain_admin_notes" id="domain_admin_notes" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('domain_admin_notes') }}</textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                        Add Domain
                    </button>
                    <a href="{{ route('admin.domains.index') }}" class="px-4 py-2 border rounded-md text-sm font-medium hover:bg-gray-50">Cancel</a>
                </div>
            </div>
        </form>
    </div>

    @if (session('trashed_domain_id'))
        <form id="restore-domain-form" method="POST" action="{{ route('admin.domains.restore', session('trashed_domain_id')) }}">
            @csrf
        </form>
    @endif
</x-admin-layout>
