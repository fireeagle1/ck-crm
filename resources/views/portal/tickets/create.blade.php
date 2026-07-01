<x-portal-layout>
    <x-slot:title>New Ticket</x-slot:title>

    <div class="max-w-2xl">
        <h1 class="text-3xl font-bold tracking-tight mb-2">Open a Support Ticket</h1>
        <p class="text-gray-500 mb-6">Describe your issue below and our team will get back to you as soon as possible.</p>

        <div class="bg-white rounded-lg border p-6">
            <form method="POST" action="{{ route('portal.tickets.store') }}">
                @csrf

                <div class="space-y-5">
                    <div>
                        <label for="subject" class="block text-sm font-semibold text-gray-700">Subject <span class="text-red-500">*</span></label>
                        <input type="text" name="subject" id="subject" required value="{{ old('subject') }}"
                               placeholder="Brief summary of your issue"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        @error('subject') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    @if ($services->isNotEmpty())
                        <div>
                            <label for="service_id" class="block text-sm font-semibold text-gray-700">Related Service</label>
                            <select name="service_id" id="service_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">General enquiry (no specific service)</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->service_id }}" {{ old('service_id') == $service->service_id ? 'selected' : '' }}>
                                        {{ $service->service_short }}{{ $service->domain_name ? " ({$service->domain_name})" : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Helps us identify the service this relates to.</p>
                        </div>
                    @endif

                    @if ($assets->isNotEmpty())
                        <div>
                            <label for="asset_id" class="block text-sm font-semibold text-gray-700">Related Device/Asset</label>
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
                                  placeholder="Please provide as much detail as possible about the issue, including any error messages or steps to reproduce."
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('description') }}</textarea>
                        @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition">
                            Submit Ticket
                        </button>
                        <a href="{{ route('portal.tickets.index') }}" class="px-5 py-2.5 border rounded-md text-sm font-semibold hover:bg-gray-50 transition">
                            Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-portal-layout>
