<x-admin-layout>
    <x-slot:title>Settings</x-slot:title>

    <h1 class="text-2xl font-semibold mb-6">Settings</h1>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-2xl">
        {{-- Current logo + remove button (separate form, outside main form) --}}
        @if ($settings['logo_path'])
            <div class="mb-6 pb-6 border-b">
                <label class="block text-sm font-medium text-gray-700 mb-2">Current Logo</label>
                <div class="mb-3 p-4 bg-gray-50 rounded-md border inline-block">
                    <img src="{{ asset('storage/' . $settings['logo_path']) }}"
                         alt="Current logo"
                         class="h-12 w-auto">
                </div>
                <div>
                    <form method="POST" action="{{ route('admin.settings.logo.delete') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 hover:underline">Remove logo</button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Main settings form --}}
        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                {{-- Site Name --}}
                <div>
                    <label for="site_name" class="block text-sm font-medium text-gray-700">Site Name</label>
                    <input type="text" name="site_name" id="site_name"
                           value="{{ old('site_name', $settings['site_name']) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('site_name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Logo Upload --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ $settings['logo_path'] ? 'Replace Logo' : 'Upload Logo' }} (landscape)</label>
                    <input type="file" name="logo" id="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                           class="block w-full text-sm text-gray-500
                                  file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0
                                  file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700
                                  hover:file:bg-blue-100">
                    <p class="text-xs text-gray-500 mt-1">PNG, JPG, SVG, or WebP. Max 2MB. Landscape orientation recommended.</p>
                    @error('logo') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="pt-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                        Save Settings
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
