<x-portal-layout>
    <x-slot:title>Service Request</x-slot:title>

    <div class="max-w-2xl">
        <h1 class="text-3xl font-bold tracking-tight mb-2">Upgrade / Service Request</h1>
        <p class="text-gray-500 mb-6">Need to upgrade, downgrade, register a domain, or transfer one? Let us know and we'll take care of it.</p>

        @if (session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        <div class="bg-white rounded-lg border p-6">
            <form method="POST" action="{{ route('portal.upgrade-request.store') }}">
                @csrf

                <div class="space-y-5">
                    {{-- Request Type --}}
                    <div>
                        <label for="request_type" class="block text-sm font-semibold text-gray-700">What would you like to do? <span class="text-red-500">*</span></label>
                        <select name="request_type" id="request_type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select a request type...</option>
                            <option value="upgrade" {{ old('request_type') === 'upgrade' ? 'selected' : '' }}>Upgrade a service</option>
                            <option value="downgrade" {{ old('request_type') === 'downgrade' ? 'selected' : '' }}>Downgrade a service</option>
                            <option value="new_domain" {{ old('request_type') === 'new_domain' ? 'selected' : '' }}>Register a new domain</option>
                            <option value="transfer_domain" {{ old('request_type') === 'transfer_domain' ? 'selected' : '' }}>Transfer a domain to us</option>
                            <option value="other" {{ old('request_type') === 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('request_type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Related Service --}}
                    @if ($services->isNotEmpty())
                        <div>
                            <label for="service_id" class="block text-sm font-semibold text-gray-700">Related Service</label>
                            <select name="service_id" id="service_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Not applicable / General</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->service_id }}" {{ old('service_id') == $service->service_id ? 'selected' : '' }}>
                                        {{ $service->service_short }}{{ $service->domain_name ? " ({$service->domain_name})" : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Details --}}
                    <div>
                        <label for="details" class="block text-sm font-semibold text-gray-700">Details <span class="text-red-500">*</span></label>
                        <textarea name="details" id="details" rows="6" required
                                  placeholder="Please describe what you'd like. For example: 'I'd like to upgrade my hosting from Starter to Business', or 'I want to register example.com'"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('details') }}</textarea>
                        @error('details') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition">
                            Submit Request
                        </button>
                        <a href="{{ route('portal.dashboard') }}" class="px-5 py-2.5 border rounded-md text-sm font-semibold hover:bg-gray-50 transition">Cancel</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p class="text-sm text-blue-800">
                <strong>Need help deciding?</strong> If you're unsure which option is right for you, just describe what you need in the details box and our team will advise you on the best approach.
            </p>
        </div>
    </div>
</x-portal-layout>
