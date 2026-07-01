<x-admin-layout>
    <x-slot:title>Add Service</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Setup Service</h1>
        <a href="{{ route('admin.services.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Services</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-3xl">
        <form method="POST" action="{{ route('admin.services.store') }}" x-data="serviceForm()">
            @csrf

            <div class="space-y-5">
                {{-- Customer & Service type --}}
                <div class="grid grid-cols-2 gap-4">
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
                        <label for="service_type" class="block text-sm font-medium text-gray-700">Service Type <span class="text-red-500">*</span></label>
                        <select name="service_type" id="service_type" required x-model="serviceType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="Technical Support">Technical Support</option>
                            <option value="Web Hosting">Web Hosting</option>
                            <option value="Domain Registration">Domain Registration</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                {{-- Service name --}}
                <div>
                    <label for="service_short" class="block text-sm font-medium text-gray-700">Service Name <span class="text-red-500">*</span></label>
                    <input type="text" name="service_short" id="service_short" required value="{{ old('service_short') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="e.g. Website Hosting – example.co.uk">
                    @error('service_short') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Stripe price --}}
                @if (!empty($stripePrices))
                    <div>
                        <label for="stripe_price_id" class="block text-sm font-medium text-gray-700">Stripe Product (optional — creates subscription)</label>
                        <select name="stripe_price_id" id="stripe_price_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">No Stripe subscription (manual billing)</option>
                            @foreach ($stripePrices as $price)
                                <option value="{{ $price['id'] }}" data-amount="{{ $price['amount'] }}" data-frequency="{{ $price['frequency'] }}"
                                    {{ old('stripe_price_id') === $price['id'] ? 'selected' : '' }}>
                                    {{ $price['label'] }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">If selected, a Stripe subscription will be created and the first invoice sent automatically.</p>
                    </div>
                @endif

                {{-- Billing --}}
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label for="service_monthly_charge" class="block text-sm font-medium text-gray-700">Monthly Charge (£)</label>
                        <input type="number" step="0.01" min="0" name="service_monthly_charge" id="service_monthly_charge"
                               value="{{ old('service_monthly_charge') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="service_payment_frequency" class="block text-sm font-medium text-gray-700">Frequency</label>
                        <select name="service_payment_frequency" id="service_payment_frequency" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">—</option>
                            <option value="Monthly" {{ old('service_payment_frequency') === 'Monthly' ? 'selected' : '' }}>Monthly</option>
                            <option value="Quarterly" {{ old('service_payment_frequency') === 'Quarterly' ? 'selected' : '' }}>Quarterly</option>
                            <option value="Annually" {{ old('service_payment_frequency') === 'Annually' ? 'selected' : '' }}>Annually</option>
                        </select>
                    </div>
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                        <input type="date" name="start_date" id="start_date" value="{{ old('start_date', date('Y-m-d')) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                {{-- Domain section (shows for Hosting & Domain Reg) --}}
                <div x-show="serviceType === 'Web Hosting' || serviceType === 'Domain Registration'" x-transition
                     class="border border-gray-200 rounded-md p-4 bg-gray-50">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Domain Details</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="domain_name" class="block text-sm font-medium text-gray-700">Domain Name</label>
                            <input type="text" name="domain_name" id="domain_name" value="{{ old('domain_name') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="example.co.uk">
                        </div>
                        <div>
                            <label for="domain_registrar" class="block text-sm font-medium text-gray-700">Registrar</label>
                            <input type="text" name="domain_registrar" id="domain_registrar" value="{{ old('domain_registrar', 'eNom') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="Active" selected>Active</option>
                        <option value="Suspended">Suspended</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
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

    <script>
        function serviceForm() {
            return {
                serviceType: '{{ old('service_type', 'Technical Support') }}',
            }
        }
    </script>
</x-admin-layout>
