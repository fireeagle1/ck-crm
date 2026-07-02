<x-admin-layout>
    <x-slot:title>General Settings</x-slot:title>

    @php
        $settingsNav = [
            ['route' => 'admin.settings.general', 'label' => 'General', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
            ['route' => 'admin.settings.import', 'label' => 'Import Data', 'icon' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12'],
            ['route' => 'admin.settings.tasks', 'label' => 'Scheduled Tasks', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ];
    @endphp

    <div class="flex gap-6">
        {{-- Sidebar --}}
        <aside class="w-56 shrink-0 hidden md:block">
            <nav class="bg-white rounded-lg shadow-sm border overflow-hidden sticky top-20">
                <div class="px-4 py-3 border-b">
                    <h2 class="text-sm font-semibold text-gray-900">Settings</h2>
                </div>
                <ul class="py-1">
                    @foreach ($settingsNav as $item)
                        <li>
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-3 px-4 py-2.5 text-sm transition
                                      {{ request()->routeIs($item['route']) ? 'bg-blue-50 text-blue-700 font-medium border-r-2 border-blue-600' : 'text-gray-700 hover:bg-gray-50' }}">
                                <svg class="w-4 h-4 shrink-0 {{ request()->routeIs($item['route']) ? 'text-blue-600' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="{{ $item['icon'] }}"/>
                                </svg>
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </aside>

        {{-- Main content --}}
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-semibold mb-6">General</h1>

            <div class="bg-white rounded-lg shadow-sm border p-6">
                {{-- Current logo + remove button --}}
                @if ($settings['logo_path'])
                    <div class="mb-6 pb-6 border-b">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Logo</label>
                        <div class="mb-3 p-4 bg-gray-50 rounded-md border inline-block">
                            <img src="{{ asset($settings['logo_path']) }}"
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
                        <div>
                            <label for="site_name" class="block text-sm font-medium text-gray-700">Site Name</label>
                            <input type="text" name="site_name" id="site_name"
                                   value="{{ old('site_name', $settings['site_name']) }}"
                                   class="mt-1 block w-full max-w-md rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('site_name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ $settings['logo_path'] ? 'Replace' : 'Upload' }} Light Logo (for dark backgrounds — nav bar, email header)</label>
                            <input type="file" name="logo" id="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                   class="block w-full max-w-md text-sm text-gray-500
                                          file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0
                                          file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700
                                          hover:file:bg-blue-100">
                            <p class="text-xs text-gray-500 mt-1">White/light logo for dark backgrounds. PNG with transparency recommended.</p>
                            @error('logo') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Dark logo --}}
                        <div>
                            @if ($settings['logo_dark_path'])
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Current Dark Logo</label>
                                    <div class="p-4 bg-white rounded-md border inline-block">
                                        <img src="{{ asset($settings['logo_dark_path']) }}" alt="Dark logo" class="h-10 w-auto">
                                    </div>
                                </div>
                            @endif
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ $settings['logo_dark_path'] ? 'Replace' : 'Upload' }} Dark Logo (for light backgrounds — login page)</label>
                            <input type="file" name="logo_dark" id="logo_dark" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                   class="block w-full max-w-md text-sm text-gray-500
                                          file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0
                                          file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700
                                          hover:file:bg-blue-100">
                            <p class="text-xs text-gray-500 mt-1">Dark/coloured logo for white backgrounds. Used on login and registration screens.</p>
                            @error('logo_dark') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Favicon --}}
                        <div>
                            @if ($settings['favicon_path'])
                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Current Favicon</label>
                                    <div class="p-3 bg-gray-50 rounded-md border inline-flex items-center gap-3">
                                        <img src="{{ asset($settings['favicon_path']) }}" alt="Favicon" class="h-8 w-8">
                                        <span class="text-xs text-gray-500">{{ $settings['favicon_path'] }}</span>
                                    </div>
                                </div>
                            @endif
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ $settings['favicon_path'] ? 'Replace' : 'Upload' }} Favicon</label>
                            <input type="file" name="favicon" id="favicon" accept=".ico,.png,.svg,image/png,image/svg+xml,image/x-icon"
                                   class="block w-full max-w-md text-sm text-gray-500
                                          file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0
                                          file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700
                                          hover:file:bg-blue-100">
                            <p class="text-xs text-gray-500 mt-1">Browser tab icon. Recommended: 32×32px .ico or .png file.</p>
                            @error('favicon') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                                Save Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
