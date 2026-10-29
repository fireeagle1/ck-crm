<x-admin-layout>
    <x-slot:title>Add App</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold">Add App</h1>
            <p class="text-sm text-gray-500">Linking to service: {{ $service->service_short }}</p>
        </div>
        <a href="{{ route('admin.services.show', $service) }}" class="text-sm text-blue-600 hover:underline">&larr; Service</a>
    </div>

    <div class="bg-white rounded-lg border p-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.customer-apps.store', $service) }}">
            @csrf

            <div class="space-y-5">
                <div>
                    <label for="repo_url" class="block text-sm font-semibold text-gray-700">Repository URL</label>
                    <input type="url" name="repo_url" id="repo_url"
                           value="{{ old('repo_url') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="https://github.com/org/repo">
                    @error('repo_url') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="hosting_location" class="block text-sm font-semibold text-gray-700">Hosting / Storage Location</label>
                    <input type="text" name="hosting_location" id="hosting_location"
                           value="{{ old('hosting_location') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="e.g. AWS eu-west-2, cPanel server, VPS hostname">
                    @error('hosting_location') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="codebase_info" class="block text-sm font-semibold text-gray-700">Codebase Information</label>
                    <textarea name="codebase_info" id="codebase_info" rows="6"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Framework, language, notable architecture, deployment notes...">{{ old('codebase_info') }}</textarea>
                    @error('codebase_info') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <p class="text-xs text-gray-400">
                    Billing, status, and the customer-facing name stay on the service record. This technical metadata is never shown to the customer.
                </p>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700">Create App</button>
                    <a href="{{ route('admin.services.show', $service) }}" class="px-5 py-2.5 border rounded-md text-sm font-semibold hover:bg-gray-50">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
