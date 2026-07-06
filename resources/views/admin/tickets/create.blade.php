<x-admin-layout>
    <x-slot:title>Create Ticket</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Create Ticket</h1>
        <a href="{{ route('admin.tickets.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Tickets</a>
    </div>

    <div class="max-w-3xl" x-data="ticketForm()">
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <form method="POST" action="{{ route('admin.tickets.store') }}">
                @csrf

                <div class="space-y-5">
                    {{-- Customer --}}
                    <div>
                        <label for="company_id" class="block text-sm font-semibold text-gray-700">Customer <span class="text-red-500">*</span></label>
                        <select name="company_id" id="company_id" required x-model="companyId" @change="loadCustomerData()"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">Select a customer...</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->company_id }}" {{ old('company_id', $selectedCustomer) == $customer->company_id ? 'selected' : '' }}>
                                    {{ $customer->company_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('company_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Ticket Type --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Type <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="relative cursor-pointer" @click="ticketType = 'Incident'">
                                <input type="radio" name="ticket_type" value="Incident" class="sr-only" :checked="ticketType === 'Incident'">
                                <div :class="ticketType === 'Incident' ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-600' : 'border-gray-200 hover:border-gray-300'"
                                     class="border rounded-lg p-3 transition">
                                    <p class="font-semibold text-gray-900 text-sm">🚨 Incident</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Something is broken or not working</p>
                                </div>
                            </label>
                            <label class="relative cursor-pointer" @click="ticketType = 'Service Request'">
                                <input type="radio" name="ticket_type" value="Service Request" class="sr-only" :checked="ticketType === 'Service Request'">
                                <div :class="ticketType === 'Service Request' ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-600' : 'border-gray-200 hover:border-gray-300'"
                                     class="border rounded-lg p-3 transition">
                                    <p class="font-semibold text-gray-900 text-sm">📋 Service Request</p>
                                    <p class="text-xs text-gray-500 mt-0.5">A change, new setup, or scheduled work</p>
                                </div>
                            </label>
                        </div>
                        <input type="hidden" name="ticket_type" :value="ticketType">
                    </div>

                    {{-- Category (for service requests) --}}
                    <div x-show="ticketType === 'Service Request'" x-transition>
                        <label for="request_category" class="block text-sm font-semibold text-gray-700">Category</label>
                        <select name="request_category" id="request_category"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">Select a category...</option>
                            @foreach (['Website Change', 'Email Setup', 'New Feature', 'Hardware', 'Software', 'Network', 'Other'] as $cat)
                                <option value="{{ $cat }}" {{ old('request_category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Subject --}}
                    <div>
                        <label for="subject" class="block text-sm font-semibold text-gray-700">Subject <span class="text-red-500">*</span></label>
                        <input type="text" name="subject" id="subject" required value="{{ old('subject') }}"
                               placeholder="Brief summary of the issue or request"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                        @error('subject') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Priority --}}
                    <div>
                        <label for="priority" class="block text-sm font-semibold text-gray-700">Priority <span class="text-red-500">*</span></label>
                        <select name="priority" id="priority" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                            @foreach (['Low', 'Normal', 'High', 'Critical'] as $p)
                                <option value="{{ $p }}" {{ old('priority', 'Normal') === $p ? 'selected' : '' }}>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Related Service --}}
                    @if ($services->isNotEmpty())
                        <div>
                            <label for="service_id" class="block text-sm font-semibold text-gray-700">Related Service</label>
                            <select name="service_id" id="service_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">None</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->service_id }}" {{ old('service_id') == $service->service_id ? 'selected' : '' }}>
                                        {{ $service->service_short }}{{ $service->domain_name ? " ({$service->domain_name})" : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Related Asset --}}
                    @if ($assets->isNotEmpty())
                        <div>
                            <label for="asset_id" class="block text-sm font-semibold text-gray-700">Related Asset</label>
                            <select name="asset_id" id="asset_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">None</option>
                                @foreach ($assets as $asset)
                                    <option value="{{ $asset->device_id }}" {{ old('asset_id') == $asset->device_id ? 'selected' : '' }}>
                                        {{ $asset->device_name }}{{ $asset->device_type ? " ({$asset->device_type})" : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Description --}}
                    <div>
                        <label for="description" class="block text-sm font-semibold text-gray-700">Description <span class="text-red-500">*</span></label>
                        <textarea name="description" id="description" rows="6" required
                                  placeholder="Describe the issue or request in detail..."
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">{{ old('description') }}</textarea>
                        @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Notify Customer --}}
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" name="notify_customer" id="notify_customer" value="1" checked
                                   class="mt-0.5 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                            <div>
                                <label for="notify_customer" class="text-sm font-semibold text-gray-700 cursor-pointer">
                                    Send confirmation email to customer
                                </label>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    The customer will receive an email confirming this ticket has been opened on their behalf.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Submit --}}
                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition">
                            Create Ticket
                        </button>
                        <a href="{{ route('admin.tickets.index') }}" class="px-5 py-2.5 border rounded-md text-sm font-semibold hover:bg-gray-50 transition">
                            Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function ticketForm() {
            return {
                companyId: '{{ old('company_id', $selectedCustomer) }}',
                ticketType: '{{ old('ticket_type', 'Incident') }}',
                loadCustomerData() {
                    // Reload page with selected customer to populate services/assets
                    if (this.companyId) {
                        window.location.href = '{{ route('admin.tickets.create') }}?customer_id=' + this.companyId;
                    }
                }
            }
        }
    </script>
</x-admin-layout>
