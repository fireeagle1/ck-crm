<x-admin-layout>
    <x-slot:title>Maintenance</x-slot:title>

    @php
        $settingsNav = [
            ['route' => 'admin.settings.general', 'label' => 'General', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
            ['route' => 'admin.settings.tasks', 'label' => 'Scheduled Tasks', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['route' => 'admin.settings.maintenance', 'label' => 'Maintenance', 'icon' => 'M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.53 1.53 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z'],
            ['route' => 'admin.mcp.tokens.index', 'label' => 'MCP Tokens', 'icon' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z'],
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
                            {{-- Use Route::has so a stale route cache (missing a newly
                                 added route) can never 500 this recovery page. --}}
                            <a href="{{ \Route::has($item['route']) ? route($item['route']) : '#' }}"
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

                <form method="POST" action="{{ url('/admin/settings/maintenance/run') }}">
                    @csrf
                    <input type="hidden" name="action" value="storage_link">
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        Rebuild storage link
                    </button>
                </form>
            </div>

            {{-- Database migrations --}}
            <div class="bg-white rounded-lg shadow-sm border p-5 mb-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-1">Database Migrations</h2>
                <p class="text-xs text-gray-500 mb-4">
                    Run any pending migrations after deploying new code — the browser equivalent of
                    <code class="bg-gray-100 px-1 py-0.5 rounded">php artisan migrate --force</code>, so you don't need a cron job or SSH.
                </p>

                @if (! $migrations['ok'])
                    <div class="mb-4 text-sm">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 text-red-700 px-2.5 py-1 text-xs font-medium">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $migrations['note'] }}
                        </span>
                    </div>
                @elseif (count($migrations['pending']) === 0)
                    <div class="mb-4 text-sm">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 text-green-700 px-2.5 py-1 text-xs font-medium">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Up to date — {{ $migrations['ran_count'] }} migration(s) applied, none pending
                        </span>
                        @if ($migrations['note'])
                            <p class="text-xs text-gray-400 mt-2">{{ $migrations['note'] }}</p>
                        @endif
                    </div>
                @else
                    @php $alreadyApplied = $migrations['already_applied'] ?? []; @endphp
                    <div class="mb-4">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 text-amber-700 px-2.5 py-1 text-xs font-medium">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ count($migrations['pending']) }} pending migration(s)
                        </span>
                        <ul class="mt-3 border rounded-md divide-y divide-gray-100 bg-gray-50/60 max-h-48 overflow-y-auto">
                            @foreach ($migrations['pending'] as $name)
                                <li class="px-3 py-1.5 font-mono text-xs flex items-center justify-between gap-2 {{ in_array($name, $alreadyApplied) ? 'text-gray-400' : 'text-gray-600' }}">
                                    <span>{{ $name }}</span>
                                    @if (in_array($name, $alreadyApplied))
                                        <span class="shrink-0 rounded bg-gray-200 text-gray-600 px-1.5 py-0.5 text-[10px] font-sans font-medium">table exists</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Drift warning: some pending migrations target tables that
                         already exist. Running migrate would fail on these — offer
                         the non-destructive history sync instead. --}}
                    @if (count($alreadyApplied) > 0)
                        <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 p-4">
                            <div class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div class="text-sm text-amber-800">
                                    <p class="font-semibold mb-1">History mismatch detected</p>
                                    <p class="text-xs leading-relaxed">
                                        {{ count($alreadyApplied) }} pending migration(s) create tables that <strong>already exist</strong> in this database
                                        (common after a database copy or restore). Running migrations would fail with
                                        <span class="font-mono">“table already exists”</span>.
                                        <strong>Sync history</strong> marks those as already applied — it only writes to the
                                        <span class="font-mono">migrations</span> table and makes <em>no schema changes</em>, so no data is touched.
                                        Afterwards, run migrations to apply any genuinely new ones.
                                    </p>
                                </div>
                            </div>

                            <form method="POST" action="{{ url('/admin/settings/maintenance/migrate-sync') }}" class="mt-3"
                                  onsubmit="return confirm('Mark {{ count($alreadyApplied) }} already-present migration(s) as applied? This only updates the migrations history table — no tables are created, altered or dropped.');">
                                @csrf
                                <label class="flex items-start gap-2 mb-3 text-xs text-amber-800">
                                    <input type="checkbox" name="confirm" value="1" required
                                           class="mt-0.5 rounded border-amber-400 text-amber-600 focus:ring-amber-500">
                                    <span>I understand this only reconciles migration history and makes no schema changes.</span>
                                </label>
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-md text-sm font-medium bg-amber-600 text-white hover:bg-amber-700 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                    Sync history ({{ count($alreadyApplied) }})
                                </button>
                            </form>
                        </div>
                    @endif
                @endif

                <form method="POST" action="{{ url('/admin/settings/maintenance/migrate') }}"
                      onsubmit="return confirm('Run pending database migrations now? This can alter data and is not easily reversible. A database backup is recommended first.');">
                    @csrf
                    <label class="flex items-start gap-2 mb-3 text-xs text-gray-600 max-w-lg">
                        <input type="checkbox" name="confirm" value="1" required
                               class="mt-0.5 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                        <span>I understand migrations change the database schema/data and can't be trivially undone. I have a recent backup.</span>
                    </label>
                    @error('confirm') <p class="text-red-600 text-xs mb-2">You must confirm before running migrations.</p> @enderror
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-md text-sm font-medium transition
                                   {{ ($migrations['ok'] && count($migrations['pending']) === 0) ? 'bg-amber-100 text-amber-700 hover:bg-amber-200' : 'bg-amber-600 text-white hover:bg-amber-700' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2 1.5 3 4 3h8c2.5 0 4-1 4-3V7M4 7c0-2 1.5-3 4-3h8c2.5 0 4 1 4 3M4 7c0 2 1.5 3 4 3h8c2.5 0 4-1 4-3"/></svg>
                        Run migrations
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
                        <form method="POST" action="{{ url('/admin/settings/maintenance/run') }}"
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
