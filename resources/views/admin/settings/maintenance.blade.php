<x-admin-layout>
    <x-slot:title>Maintenance</x-slot:title>

    @php
        $settingsNav = [
            ['route' => 'admin.settings.general', 'label' => 'General', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
            ['route' => 'admin.settings.tasks', 'label' => 'Scheduled Tasks', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['route' => 'admin.settings.maintenance', 'label' => 'Maintenance', 'icon' => 'M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.53 1.53 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z'],
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
            <h1 class="text-2xl font-semibold mb-2">Maintenance</h1>
            <p class="text-sm text-gray-500 mb-6">Run housekeeping tasks after a deployment. These replace the artisan commands you would normally run over SSH.</p>

            {{-- Storage link status + rebuild --}}
            <div class="bg-white rounded-lg shadow-sm border p-5 mb-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-1">Storage Link</h2>
                <p class="text-xs text-gray-500 mb-4">
                    Uploaded images (product photos, ticket attachments) are served from
                    <code class="bg-gray-100 px-1 py-0.5 rounded">storage/app/public</code> via the
                    <code class="bg-gray-100 px-1 py-0.5 rounded">public/storage</code> symlink. A deploy that
                    overwrites <code class="bg-gray-100 px-1 py-0.5 rounded">public/</code> can remove it — rebuild it here.
                </p>

                <div class="mb-4 text-sm">
                    @if ($storageLink['is_symlink'] && realpath($storageLink['target']) === realpath($storageLink['expected_target']))
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 text-green-700 px-2.5 py-1 text-xs font-medium">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Symlink present and correct
                        </span>
                    @elseif ($storageLink['exists'])
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 text-amber-700 px-2.5 py-1 text-xs font-medium">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            public/storage exists but is not the expected symlink
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 text-red-700 px-2.5 py-1 text-xs font-medium">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            Symlink missing
                        </span>
                    @endif
                    <p class="text-xs text-gray-400 mt-2">
                        Even if the symlink can't be created (some hosts block it), images still load through the built-in PHP storage fallback route.
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.settings.maintenance.run') }}">
                    @csrf
                    <input type="hidden" name="action" value="storage_link">
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        Rebuild storage link
                    </button>
                </form>
            </div>

            {{-- Cache maintenance --}}
            <div class="bg-white rounded-lg shadow-sm border p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-1">Caches</h2>
                <p class="text-xs text-gray-500 mb-4">Run these after deploying new code or changing configuration so the app picks up the changes.</p>

                @php
                    $actions = [
                        ['action' => 'optimize_clear', 'label' => 'Clear all caches', 'primary' => true,  'desc' => 'config, route, view & app cache'],
                        ['action' => 'config_clear',   'label' => 'Clear config',     'primary' => false, 'desc' => 'reload .env / config changes'],
                        ['action' => 'config_cache',   'label' => 'Cache config',     'primary' => false, 'desc' => 'rebuild cached config'],
                        ['action' => 'route_clear',    'label' => 'Clear routes',     'primary' => false, 'desc' => 'drop cached route table'],
                        ['action' => 'route_cache',    'label' => 'Cache routes',     'primary' => false, 'desc' => 'rebuild cached routes'],
                        ['action' => 'cache_clear',    'label' => 'Clear app cache',  'primary' => false, 'desc' => 'application cache store'],
                        ['action' => 'view_clear',     'label' => 'Clear views',      'primary' => false, 'desc' => 'compiled Blade templates'],
                    ];
                @endphp

                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach ($actions as $a)
                        <form method="POST" action="{{ route('admin.settings.maintenance.run') }}"
                              class="flex items-center justify-between gap-3 border rounded-md px-3 py-2.5 {{ $a['primary'] ? 'border-blue-200 bg-blue-50/40' : 'border-gray-200' }}">
                            @csrf
                            <input type="hidden" name="action" value="{{ $a['action'] }}">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-800">{{ $a['label'] }}</div>
                                <div class="text-xs text-gray-400 truncate">{{ $a['desc'] }}</div>
                            </div>
                            <button type="submit"
                                    class="shrink-0 px-3 py-1.5 rounded-md text-sm font-medium transition
                                           {{ $a['primary'] ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                                Run
                            </button>
                        </form>
                    @endforeach
                </div>

                <p class="text-xs text-gray-400 mt-4">
                    Tip after a deploy: <span class="font-medium text-gray-500">Clear all caches</span>, then rebuild the storage link if needed.
                </p>
            </div>
        </div>
    </div>
</x-admin-layout>
