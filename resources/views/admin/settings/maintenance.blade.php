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

            {{-- MCP / Passport setup --}}
            <div class="bg-white rounded-lg shadow-sm border p-5 mb-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-1">ChatGPT MCP / Passport Setup</h2>
                <p class="text-xs text-gray-500 mb-4">
                    One-time setup for the OpenAI connector, done from the browser so you don't need SSH. Complete these in order:
                    run migrations (creates the <span class="font-mono">oauth_*</span> tables), generate signing keys, then create the client.
                </p>

                {{-- Readiness checklist --}}
                <ol class="mb-5 space-y-2 text-sm">
                    @php
                        $steps = [
                            ['done' => $passport['tables_exist'],  'label' => 'OAuth tables exist', 'hint' => 'Run migrations above if missing'],
                            ['done' => $passport['keys_exist'],    'label' => 'Passport signing keys present', 'hint' => 'storage/oauth-*.key'],
                            ['done' => $passport['client_exists'], 'label' => 'mcp_client created', 'hint' => 'the ID OpenAI connects with'],
                        ];
                    @endphp
                    @foreach ($steps as $i => $s)
                        <li class="flex items-center gap-2">
                            @if ($s['done'])
                                <span class="inline-flex w-5 h-5 items-center justify-center rounded-full bg-green-100 text-green-700">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </span>
                            @else
                                <span class="inline-flex w-5 h-5 items-center justify-center rounded-full bg-gray-200 text-gray-500 text-[11px] font-semibold">{{ $i + 1 }}</span>
                            @endif
                            <span class="{{ $s['done'] ? 'text-gray-700' : 'text-gray-500' }}">{{ $s['label'] }}</span>
                            <span class="text-xs text-gray-400">— {{ $s['hint'] }}</span>
                        </li>
                    @endforeach
                </ol>

                <div class="space-y-4">
                    {{-- Generate keys --}}
                    <div class="border rounded-md p-3">
                        <div class="text-sm font-medium text-gray-800 mb-1">Generate Passport keys</div>
                        <p class="text-xs text-gray-400 mb-2">Writes the signing keys to <span class="font-mono">storage/</span>. Required before any OAuth request works.</p>
                        <form method="POST" action="{{ url('/admin/settings/maintenance/passport-keys') }}"
                              onsubmit="return confirm('Generate Passport keys now?');">
                            @csrf
                            @if ($passport['keys_exist'])
                                <label class="flex items-start gap-2 mb-2 text-xs text-red-600">
                                    <input type="checkbox" name="force" value="1" class="mt-0.5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    <span>Overwrite existing keys — <strong>this invalidates every issued token</strong>.</span>
                                </label>
                            @endif
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition
                                           {{ $passport['keys_exist'] ? 'bg-gray-100 text-gray-700 hover:bg-gray-200' : 'bg-blue-600 text-white hover:bg-blue-700' }}">
                                {{ $passport['keys_exist'] ? 'Regenerate keys' : 'Generate keys' }}
                            </button>
                        </form>
                    </div>

                    {{-- Create / recover / reset client --}}
                    <div class="border rounded-md p-3">
                        <div class="text-sm font-medium text-gray-800 mb-1">MCP client</div>
                        @if ($passport['client_exists'])
                            @php $c = $passport['client']; @endphp
                            <p class="text-xs text-gray-500 mb-2">An <span class="font-mono">mcp_client</span> already exists. Its details are below — copy the Client ID into OpenAI's connector (no secret).</p>

                            {{-- Recover the existing details --}}
                            <div class="rounded-md bg-gray-50 border p-3 mb-3 text-xs space-y-1.5">
                                <div class="flex gap-2">
                                    <span class="w-24 shrink-0 text-gray-400">Client ID</span>
                                    <span class="font-mono text-gray-800 break-all select-all">{{ $c['id'] }}</span>
                                </div>
                                <div class="flex gap-2">
                                    <span class="w-24 shrink-0 text-gray-400">Redirect URI(s)</span>
                                    <span class="font-mono text-gray-700 break-all">{{ implode(', ', $c['redirects']) ?: '—' }}</span>
                                </div>
                                <div class="flex gap-2">
                                    <span class="w-24 shrink-0 text-gray-400">Secret</span>
                                    <span class="text-gray-500">(none — public client, PKCE)</span>
                                </div>
                                @if ($c['revoked'])
                                    <div class="text-red-600 font-medium">This client is revoked — reset it below to issue a working one.</div>
                                @endif
                            </div>
                            <p class="text-xs text-gray-400 mb-3">
                                Manage/revoke issued tokens at <a href="{{ url('/admin/mcp/tokens') }}" class="underline">MCP tokens</a>.
                                (That page only lists tokens created after a user authorizes — it won't show the client itself.)
                            </p>

                            {{-- Reset: delete + recreate --}}
                            <details class="mt-2">
                                <summary class="cursor-pointer text-xs font-medium text-red-600">Reset client (lost ID or need a fresh one)</summary>
                                <div class="mt-3 rounded-md border border-red-200 bg-red-50 p-3">
                                    <p class="text-xs text-red-700 mb-3">
                                        Deletes the current <span class="font-mono">mcp_client</span> and its tokens, then creates a new one with a new Client ID.
                                        <strong>OpenAI's connector must be reconfigured</strong> with the new ID afterwards.
                                    </p>
                                    <form method="POST" action="{{ url('/admin/settings/maintenance/mcp-client-reset') }}"
                                          onsubmit="return confirm('Reset the MCP client? The current Client ID and all its tokens will stop working, and you must reconfigure OpenAI with the new ID.');">
                                        @csrf
                                        <input type="url" name="redirect_uri" required
                                               value="{{ $c['redirects'][0] ?? 'https://chat.openai.com/connector_platform_oauth_redirect' }}"
                                               class="block w-full mb-2 rounded-md border-gray-300 shadow-sm text-sm focus:ring-red-500 focus:border-red-500">
                                        <label class="flex items-start gap-2 mb-2 text-xs text-red-700">
                                            <input type="checkbox" name="confirm" value="1" required class="mt-0.5 rounded border-red-400 text-red-600 focus:ring-red-500">
                                            <span>I understand this invalidates the existing client and tokens.</span>
                                        </label>
                                        <button type="submit" class="px-3 py-1.5 rounded-md text-sm font-medium bg-red-600 text-white hover:bg-red-700 transition">
                                            Reset client
                                        </button>
                                    </form>
                                </div>
                            </details>
                        @else
                            <p class="text-xs text-gray-400 mb-2">Creates the public (PKCE, no-secret) client. Paste the OpenAI connector's callback URL.</p>
                            <form method="POST" action="{{ url('/admin/settings/maintenance/mcp-client') }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <input type="url" name="redirect_uri" required
                                       value="https://chat.openai.com/connector_platform_oauth_redirect"
                                       class="flex-1 min-w-[18rem] rounded-md border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                                <button type="submit" class="px-3 py-2 rounded-md text-sm font-medium bg-blue-600 text-white hover:bg-blue-700 transition">
                                    Create client
                                </button>
                            </form>
                            @error('redirect_uri') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-gray-400 mt-2">The resulting Client ID appears in the green banner at the top — copy it into OpenAI's connector (no secret needed).</p>
                        @endif
                    </div>
                </div>
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
