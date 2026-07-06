<x-portal-layout>
    <x-slot:title>New Ticket</x-slot:title>

    <div class="max-w-2xl" x-data="{ ticketType: '{{ old('ticket_type', 'Incident') }}' }">
        <h1 class="text-3xl font-bold tracking-tight mb-2">Raise a Request</h1>
        <p class="text-gray-500 mb-6">Tell us what you need help with and our team will get back to you.</p>

        {{-- Type selection (only for support plan customers) --}}
        @if ($hasSupportPlan)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <label class="relative cursor-pointer" @click="ticketType = 'Incident'">
                    <input type="radio" name="ticket_type_display" value="Incident" class="sr-only" :checked="ticketType === 'Incident'">
                    <div :class="ticketType === 'Incident' ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-600' : 'border-gray-200 hover:border-gray-300'"
                         class="border rounded-lg p-4 transition">
                        <p class="font-semibold text-gray-900">🚨 Incident</p>
                        <p class="text-sm text-gray-500 mt-1">Something is broken or not working as expected. Report an issue that needs fixing.</p>
                    </div>
                </label>
                <label class="relative cursor-pointer" @click="ticketType = 'Service Request'">
                    <input type="radio" name="ticket_type_display" value="Service Request" class="sr-only" :checked="ticketType === 'Service Request'">
                    <div :class="ticketType === 'Service Request' ? 'border-blue-600 bg-blue-50 ring-2 ring-blue-600' : 'border-gray-200 hover:border-gray-300'"
                         class="border rounded-lg p-4 transition">
                        <p class="font-semibold text-gray-900">📋 Service Request</p>
                        <p class="text-sm text-gray-500 mt-1">Request a change, new setup, or scheduled work. E.g. website update, new email account.</p>
                    </div>
                </label>
            </div>
        @endif

        <div class="bg-white rounded-lg border p-6">
            <form method="POST" action="{{ route('portal.tickets.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="ticket_type" :value="ticketType">

                <div class="space-y-5">
                    {{-- Category (for service requests) --}}
                    <div x-show="ticketType === 'Service Request'" x-transition>
                        <label for="request_category" class="block text-sm font-semibold text-gray-700">Category</label>
                        <select name="request_category" id="request_category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select a category...</option>
                            <option value="Website Change" {{ old('request_category') === 'Website Change' ? 'selected' : '' }}>Website Change</option>
                            <option value="Email Setup" {{ old('request_category') === 'Email Setup' ? 'selected' : '' }}>Email Setup</option>
                            <option value="New Feature" {{ old('request_category') === 'New Feature' ? 'selected' : '' }}>New Feature</option>
                            <option value="Hardware" {{ old('request_category') === 'Hardware' ? 'selected' : '' }}>Hardware</option>
                            <option value="Software" {{ old('request_category') === 'Software' ? 'selected' : '' }}>Software</option>
                            <option value="Network" {{ old('request_category') === 'Network' ? 'selected' : '' }}>Network</option>
                            <option value="Other" {{ old('request_category') === 'Other' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>

                    <div>
                        <label for="subject" class="block text-sm font-semibold text-gray-700">Subject <span class="text-red-500">*</span></label>
                        <input type="text" name="subject" id="subject" required value="{{ old('subject') }}"
                               placeholder="Brief summary of your issue or request"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('subject') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    @if ($services->isNotEmpty())
                        <div>
                            <label for="service_id" class="block text-sm font-semibold text-gray-700">Related Service</label>
                            <select name="service_id" id="service_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">General (no specific service)</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->service_id }}" {{ old('service_id') == $service->service_id ? 'selected' : '' }}>
                                        {{ $service->service_short }}{{ $service->domain_name ? " ({$service->domain_name})" : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($assets->isNotEmpty())
                        <div>
                            <label for="asset_id" class="block text-sm font-semibold text-gray-700">Related Device</label>
                            <select name="asset_id" id="asset_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">None</option>
                                @foreach ($assets as $asset)
                                    <option value="{{ $asset->device_id }}" {{ old('asset_id') == $asset->device_id ? 'selected' : '' }}>
                                        {{ $asset->device_name }}{{ $asset->device_type ? " ({$asset->device_type})" : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label for="description" class="block text-sm font-semibold text-gray-700">Description <span class="text-red-500">*</span></label>
                        <textarea name="description" id="description" rows="6" required
                                  placeholder="Please provide as much detail as possible..."
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('description') }}</textarea>
                        @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="attachment" class="block text-sm font-semibold text-gray-700">Attachment</label>
                        <input type="file" name="attachment" id="attachment"
                               class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-1">Optional. Max 10MB.</p>
                        @error('attachment') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition">
                            Submit
                        </button>
                        <a href="{{ route('portal.tickets.index') }}" class="px-5 py-2.5 border rounded-md text-sm font-semibold hover:bg-gray-50 transition">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-portal-layout>
