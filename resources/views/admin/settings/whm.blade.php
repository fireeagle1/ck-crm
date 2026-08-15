<x-admin-layout>
    <x-slot:title>WHM Configuration</x-slot:title>

    @php
        $settingsNav = [
            ['route' => 'admin.settings.general', 'label' => 'General', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
            ['route' => 'admin.settings.tasks', 'label' => 'Scheduled Tasks', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['route' => 'admin.settings.whm', 'label' => 'WHM / Hosting', 'icon' => 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01'],
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
            <h1 class="text-2xl font-semibold mb-6">WHM / Hosting Configuration</h1>

            {{-- Success message --}}
            @if (session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm text-green-700 font-medium">{{ session('success') }}</p>
                    </div>
                </div>
            @endif

            {{-- Connection error --}}
            @if ($errors->has('whm_connection'))
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm text-red-700 font-medium">{{ $errors->first('whm_connection') }}</p>
                    </div>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm border p-6">
                <p class="text-sm text-gray-600 mb-6">Configure WHM server connection details for automatic hosting provisioning. Credentials are tested before saving and stored encrypted.</p>

                <form method="POST" action="{{ route('admin.settings.whm.update') }}">
                    @csrf

                    <div class="space-y-6">
                        {{-- Server Hostname --}}
                        <div>
                            <label for="whm_hostname" class="block text-sm font-medium text-gray-700">Server Hostname</label>
                            <input type="text" name="whm_hostname" id="whm_hostname"
                                   value="{{ old('whm_hostname', $settings['whm_hostname']) }}"
                                   placeholder="server.example.com"
                                   class="mt-1 block w-full max-w-md rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-xs text-gray-500 mt-1">The hostname or IP of your WHM/cPanel server (without https:// or port).</p>
                            @error('whm_hostname') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- API Token --}}
                        <div>
                            <label for="whm_api_token" class="block text-sm font-medium text-gray-700">API Token</label>
                            <input type="password" name="whm_api_token" id="whm_api_token"
                                   value="{{ old('whm_api_token', $settings['whm_api_token']) }}"
                                   placeholder="Enter WHM API token"
                                   class="mt-1 block w-full max-w-md rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Generate this in WHM under Manage API Tokens. Stored encrypted.</p>
                            @error('whm_api_token') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Default Package --}}
                        <div>
                            <label for="whm_default_package" class="block text-sm font-medium text-gray-700">Default Hosting Package</label>
                            <input type="text" name="whm_default_package" id="whm_default_package"
                                   value="{{ old('whm_default_package', $settings['whm_default_package']) }}"
                                   placeholder="default"
                                   class="mt-1 block w-full max-w-md rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-xs text-gray-500 mt-1">The cPanel package name to use when provisioning new accounts.</p>
                            @error('whm_default_package') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Nameservers --}}
                        <div class="border-t pt-6">
                            <h3 class="text-sm font-medium text-gray-900 mb-4">Nameservers</h3>

                            <div class="space-y-4">
                                <div>
                                    <label for="whm_nameserver_0" class="block text-sm font-medium text-gray-700">Primary Nameserver (NS0)</label>
                                    <input type="text" name="whm_nameserver_0" id="whm_nameserver_0"
                                           value="{{ old('whm_nameserver_0', $settings['whm_nameserver_0']) }}"
                                           placeholder="ns0.thundercloud.uk"
                                           class="mt-1 block w-full max-w-md rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    @error('whm_nameserver_0') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="whm_nameserver_1" class="block text-sm font-medium text-gray-700">Secondary Nameserver (NS1)</label>
                                    <input type="text" name="whm_nameserver_1" id="whm_nameserver_1"
                                           value="{{ old('whm_nameserver_1', $settings['whm_nameserver_1']) }}"
                                           placeholder="ns1.thundercloud.uk"
                                           class="mt-1 block w-full max-w-md rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    @error('whm_nameserver_1') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 border-t">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                                Test Connection & Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
