<x-admin-layout>
    <x-slot:title>Add Service</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Add Service</h1>
        <a href="{{ route('admin.services.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Services</a>
    </div>

    <div class="bg-white rounded-lg border p-6 max-w-3xl">
        <form method="POST" action="{{ route('admin.services.store') }}" x-data="serviceForm()">
            @csrf

            <div class="space-y-5">
                {{-- Customer & Service type --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="company_id" class="block text-sm font-semibold text-gray-700">Customer <span class="text-red-500">*</span></label>
                        <select name="company_id" id="company_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select customer...</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->company_id }}" {{ old('company_id', request('company_id')) == $customer->company_id ? 'selected' : '' }}>
                                    {{ $customer->company_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('company_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="service_type" class="block text-sm font-semibold text-gray-700">Service Type <span class="text-red-500">*</span></label>
                        <select name="service_type" id="service_type" required x-model="serviceType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="Web Hosting">Web Hosting</option>
                            <option value="Technical Support">Technical Support</option>
                            <option value="Other">Other</option>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Domain registrations are managed in the Domains section, not as services.</p>
                    </div>
                </div>

                {{-- Service name (auto-fills from Stripe selection) --}}
                <div>
                    <label for="service_short" class="block text-sm font-semibold text-gray-700">Service Name <span class="text-red-500">*</span></label>
                    <input type="text" name="service_short" id="service_short" required x-model="serviceName"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           :class="stripeSelected ? 'bg-gray-100 text-gray-500' : ''"
                           :readonly="stripeSelected"
                           placeholder="Auto-filled from Stripe product">
                    <p class="text-xs text-gray-400 mt-1" x-show="stripeSelected">Name set by Stripe product. Change the product in Stripe to update.</p>
                    @error('service_short') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Hosting details (shows for Web Hosting) --}}
                <div x-show="serviceType === 'Web Hosting'" x-transition
                     class="border border-blue-200 rounded-md p-4 bg-blue-50">
                    <h3 class="text-sm font-semibold text-blue-900 mb-3">Hosting Details</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="domain_name" class="block text-sm font-medium text-gray-700">Domain Name <span class="text-red-500">*</span></label>
                            <input type="text" name="domain_name" id="domain_name" value="{{ old('domain_name') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="example.co.uk"
                                   :required="serviceType === 'Web Hosting'"
                                   @input="generateUsername()">
                            @error('domain_name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="cpanel_username" class="block text-sm font-medium text-gray-700">cPanel Username <span class="text-red-500">*</span></label>
                            <input type="text" name="cpanel_username" id="cpanel_username" x-model="cpanelUsername"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Auto-generated from domain"
                                   :required="serviceType === 'Web Hosting'" maxlength="16">
                            <p class="text-xs text-gray-400 mt-1">Max 16 chars. Auto-generated — edit if needed.</p>
                        </div>
                    </div>

                    {{-- WHM Provisioning --}}
                    <div class="mt-4 pt-4 border-t border-blue-200">
                        <div class="flex items-center gap-2 mb-3">
                            <input type="checkbox" name="provision_whm" id="provision_whm" value="1"
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                   {{ old('provision_whm') ? 'checked' : '' }}>
                            <label for="provision_whm" class="text-sm font-semibold text-blue-900">Create cPanel account in WHM</label>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="whm_package" class="block text-sm font-medium text-gray-700">WHM Package</label>
                                <select name="whm_package" id="whm_package" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select package...</option>
                                    <option value="ckhostco_Basic" {{ old('whm_package') === 'ckhostco_Basic' ? 'selected' : '' }}>Basic</option>
                                    <option value="ckhostco_Personal" {{ old('whm_package') === 'ckhostco_Personal' ? 'selected' : '' }}>Personal</option>
                                    <option value="ckhostco_Personal Plus" {{ old('whm_package') === 'ckhostco_Personal Plus' ? 'selected' : '' }}>Personal Plus</option>
                                    <option value="ckhostco_Business Plus" {{ old('whm_package') === 'ckhostco_Business Plus' ? 'selected' : '' }}>Business Plus</option>
                                    <option value="ckhostco_Unlimited" {{ old('whm_package') === 'ckhostco_Unlimited' ? 'selected' : '' }}>Unlimited</option>
                                </select>
                            </div>
                            <div>
                                <label for="contact_email" class="block text-sm font-medium text-gray-700">Contact Email</label>
                                <input type="email" name="contact_email" id="contact_email" x-model="contactEmail"
                                       placeholder="customer@example.com"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <p class="text-xs text-gray-400 mt-1">Auto-filled from customer account.</p>
                            </div>
                        </div>
                        <p class="text-xs text-blue-700 mt-2">If ticked, a cPanel account will be created on your WHM server with a random password. The customer can sign in via SSO from the portal.</p>
                    </div>
                </div>

                {{-- Stripe price --}}
                @if (!empty($stripePrices))
                    <div>
                        <label for="stripe_price_id" class="block text-sm font-semibold text-gray-700">Stripe Subscription <span class="text-red-500">*</span></label>
                        <select name="stripe_price_id" id="stripe_price_id" @change="onStripeChange($event)"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="" data-name="">Select a Stripe product...</option>
                            @foreach ($stripePrices as $price)
                                <option value="{{ $price['id'] }}" data-name="{{ $price['product_name'] }}"
                                    {{ old('stripe_price_id') === $price['id'] ? 'selected' : '' }}>
                                    {{ $price['label'] }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">The service name and billing will be managed by Stripe.</p>
                    </div>
                @endif

                {{-- Start date --}}
                <div class="max-w-xs">
                    <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                    <input type="date" name="start_date" id="start_date" value="{{ old('start_date', date('Y-m-d')) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700">
                        Create Service
                    </button>
                    <a href="{{ route('admin.services.index') }}" class="px-5 py-2.5 border rounded-md text-sm font-semibold hover:bg-gray-50">Cancel</a>
                </div>
            </div>
        </form>
    </div>

    <script>
        function serviceForm() {
            return {
                serviceType: '{{ old('service_type', 'Web Hosting') }}',
                cpanelUsername: '{{ old('cpanel_username') }}',
                contactEmail: '{{ old('contact_email') }}',
                serviceName: '{{ old('service_short') }}',
                stripeSelected: false,
                customerEmails: @json($customerEmails),
                existingUsernames: @json($existingUsernames),

                onStripeChange(event) {
                    const option = event.target.selectedOptions[0];
                    const name = option?.dataset?.name || '';
                    if (name) {
                        this.serviceName = name;
                        this.stripeSelected = true;
                    } else {
                        this.stripeSelected = false;
                    }
                },

                generateUsername() {
                    const domain = document.getElementById('domain_name').value.trim().toLowerCase();
                    if (!domain) return;

                    let sld = domain.split('.')[0].replace(/[^a-z0-9]/g, '');
                    let base = 'ckhostco_' + sld;
                    if (base.length > 16) base = base.substring(0, 16);

                    let username = base;
                    let counter = 1;
                    while (this.existingUsernames.includes(username)) {
                        username = base.substring(0, 14) + counter;
                        counter++;
                    }

                    this.cpanelUsername = username;
                },

                init() {
                    const select = document.getElementById('company_id');
                    if (select) {
                        select.addEventListener('change', () => {
                            const id = select.value;
                            if (id && this.customerEmails[id]) {
                                this.contactEmail = this.customerEmails[id];
                            }
                        });
                        if (select.value && this.customerEmails[select.value] && !this.contactEmail) {
                            this.contactEmail = this.customerEmails[select.value];
                        }
                    }
                    // Check if Stripe was pre-selected (old value)
                    const stripeSelect = document.getElementById('stripe_price_id');
                    if (stripeSelect && stripeSelect.value) {
                        this.stripeSelected = true;
                        const opt = stripeSelect.selectedOptions[0];
                        if (opt?.dataset?.name && !this.serviceName) {
                            this.serviceName = opt.dataset.name;
                        }
                    }
                }
            }
        }
    </script>
</x-admin-layout>
