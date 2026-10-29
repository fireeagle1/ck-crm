<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function general(): View
    {
        $settings = [
            'site_name' => Setting::get('site_name', 'CK Enterprises UK'),
            'logo_path' => Setting::get('logo_path'),
            'logo_dark_path' => Setting::get('logo_dark_path'),
            'favicon_path' => Setting::get('favicon_path'),
            'terms_conditions' => Setting::get('terms_conditions', ''),
        ];

        return view('admin.settings.general', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'site_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'logo_dark' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'favicon' => 'nullable|mimes:ico,png|max:512',
            'terms_conditions' => 'nullable|string|max:65000',
        ]);

        Setting::set('site_name', $request->input('site_name'));
        Setting::set('terms_conditions', $request->input('terms_conditions'));

        // Light/transparent logo (for dark backgrounds — header, nav)
        if ($request->hasFile('logo')) {
            $oldPath = Setting::get('logo_path');
            if ($oldPath && file_exists(public_path($oldPath))) {
                unlink(public_path($oldPath));
            }

            $file = $request->file('logo');
            $filename = 'logo-' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('branding'), $filename);
            Setting::set('logo_path', 'branding/' . $filename);
        }

        // Dark logo (for light backgrounds — login page, emails)
        if ($request->hasFile('logo_dark')) {
            $oldPath = Setting::get('logo_dark_path');
            if ($oldPath && file_exists(public_path($oldPath))) {
                unlink(public_path($oldPath));
            }

            $file = $request->file('logo_dark');
            $filename = 'logo-dark-' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('branding'), $filename);
            Setting::set('logo_dark_path', 'branding/' . $filename);
        }

        // Favicon
        if ($request->hasFile('favicon')) {
            $oldPath = Setting::get('favicon_path');
            if ($oldPath && file_exists(public_path($oldPath))) {
                unlink(public_path($oldPath));
            }

            $file = $request->file('favicon');
            $filename = 'favicon-' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('branding'), $filename);
            Setting::set('favicon_path', 'branding/' . $filename);
        }

        return back()->with('success', 'Settings saved.');
    }

    public function deleteLogo()
    {
        $path = Setting::get('logo_path');

        if ($path && file_exists(public_path($path))) {
            unlink(public_path($path));
        }

        Setting::set('logo_path', null);

        return back()->with('success', 'Logo removed.');
    }

    public function import(): View
    {
        return view('admin.settings.import');
    }

    public function scheduledTasks(): View
    {
        $logs = \App\Models\ScheduledTaskLog::orderByDesc('created_at')->paginate(30);

        // Get the defined schedule
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $events = collect($schedule->events())->map(function ($event) {
            return [
                'command' => $event->command ?? $event->description ?? 'Closure',
                'expression' => $event->expression,
                'description' => $event->description,
            ];
        });

        // Available commands that can be run on demand
        $runnableCommands = [
            'stripe:sync' => 'Stripe Sync',
            'enom:sync' => 'eNom Domain Sync',
        ];

        return view('admin.settings.scheduled-tasks', compact('logs', 'events', 'runnableCommands'));
    }

    public function runTask(Request $request)
    {
        $request->validate([
            'command' => 'required|string|in:stripe:sync,enom:sync',
        ]);

        $command = $request->input('command');

        // Run with --debug for enom to capture response info
        $params = $command === 'enom:sync' ? ['--debug' => true] : [];

        \Illuminate\Support\Facades\Artisan::call($command, $params);
        $output = \Illuminate\Support\Facades\Artisan::output();

        return back()->with('success', "Ran '{$command}'. Output: " . trim($output));
    }

    /**
     * Maintenance panel — for no-SSH (cPanel) deployments. Lets an admin run
     * the artisan cache/config/route housekeeping and rebuild the storage
     * symlink from the browser after a deploy.
     */
    public function maintenance(): View
    {
        $publicLink = public_path('storage');
        $storageLink = [
            'exists' => is_link($publicLink) || is_dir($publicLink),
            'is_symlink' => is_link($publicLink),
            'target' => is_link($publicLink) ? readlink($publicLink) : null,
            'expected_target' => storage_path('app/public'),
        ];

        $migrations = $this->migrationStatus();
        $passport = $this->passportStatus();

        return view('admin.settings.maintenance', compact('storageLink', 'migrations', 'passport'));
    }

    /**
     * Report Passport/MCP readiness for the panel: whether the oauth tables
     * exist, whether the signing keys are present, and whether the mcp_client
     * has been created. Each check degrades to false on error so the page
     * always renders.
     */
    private function passportStatus(): array
    {
        $tablesExist = false;
        $client = null;

        try {
            $tablesExist = \Illuminate\Support\Facades\Schema::hasTable('oauth_clients')
                && \Illuminate\Support\Facades\Schema::hasTable('oauth_access_tokens');

            if ($tablesExist) {
                $row = \Illuminate\Support\Facades\DB::table('oauth_clients')
                    ->where('name', 'mcp_client')
                    ->first();

                if ($row) {
                    // redirect_uris is stored as JSON (or legacy comma list).
                    $redirects = json_decode($row->redirect_uris ?? '[]', true);
                    if (! is_array($redirects)) {
                        $redirects = array_filter(array_map('trim', explode(',', (string) $row->redirect_uris)));
                    }

                    $client = [
                        'id'        => $row->id,
                        'redirects' => array_values($redirects),
                        'revoked'   => (bool) ($row->revoked ?? false),
                    ];
                }
            }
        } catch (\Throwable) {
            // leave defaults
        }

        return [
            'tables_exist'  => $tablesExist,
            'keys_exist'    => file_exists(storage_path('oauth-private.key'))
                && file_exists(storage_path('oauth-public.key')),
            'client_exists' => $client !== null,
            'client'        => $client,
        ];
    }

    /**
     * Summarise migration state for the panel: how many have run, and the
     * names of any that are still pending. Degrades gracefully if the DB or
     * migrations table isn't reachable.
     */
    private function migrationStatus(): array
    {
        try {
            $migrator = app('migrator');
            $repository = $migrator->getRepository();

            if (! $repository->repositoryExists()) {
                return ['ok' => true, 'ran_count' => 0, 'pending' => [], 'note' => 'Migrations table not created yet — running will initialise it.'];
            }

            $ran = $repository->getRan();

            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $pending = array_values(array_filter(
                array_keys($files),
                fn ($name) => ! in_array($name, $ran, true)
            ));

            // Detect drift: pending "create table" migrations whose table ALREADY
            // exists in the DB. These would fail with "table already exists" if
            // run — they just need their history record syncing, not re-running.
            // Happens when a DB is restored/copied but its migrations table isn't.
            $alreadyApplied = array_values(array_filter(
                $pending,
                fn ($name) => $this->migrationTargetExists($name)
            ));

            return [
                'ok' => true,
                'ran_count' => count($ran),
                'pending' => $pending,
                'already_applied' => $alreadyApplied,
                'note' => null,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'ran_count' => 0, 'pending' => [], 'already_applied' => [], 'note' => 'Could not read migration status: ' . $e->getMessage()];
        }
    }

    /**
     * Best-effort check of whether a "create_<table>_table" migration's target
     * table already exists. Only used to flag already-applied create migrations
     * for history syncing; a false negative simply means it isn't offered for
     * sync (and a real run would create it), so this is conservative by design.
     */
    private function migrationTargetExists(string $migrationName): bool
    {
        if (! preg_match('/create_(.+)_table$/', $migrationName, $m)) {
            return false;
        }

        try {
            return \Illuminate\Support\Facades\Schema::hasTable($m[1]);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Run pending migrations with --force (non-interactive). Guarded behind an
     * explicit confirmation checkbox because migrations can alter data and are
     * not trivially reversible. Raises the execution time limit for the slow
     * PHP-CGI defaults common on shared/cPanel hosts.
     */
    public function runMigrations(Request $request)
    {
        $request->validate([
            'confirm' => 'accepted',
        ]);

        @set_time_limit(300);

        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $output = trim(\Illuminate\Support\Facades\Artisan::output());

            return back()->with('success', 'Migrations run. ' . ($output !== '' ? $output : 'Nothing to migrate.'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Migration failed: ' . $e->getMessage());
        }
    }

    /**
     * Dry-run: show the exact SQL that pending migrations WOULD execute,
     * without touching the database (artisan `migrate --pretend`). Lets an
     * admin spot "table/column already exists" collisions before running for
     * real — essential when a copied/restored DB has drifted from its ledger.
     */
    public function previewMigrations()
    {
        @set_time_limit(120);

        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true, '--pretend' => true]);
            $output = trim(\Illuminate\Support\Facades\Artisan::output());

            return back()->with('migration_preview', $output !== '' ? $output : 'Nothing pending — no SQL would run.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Preview failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate the Passport signing keys (storage/oauth-private.key and
     * oauth-public.key) from the browser — the no-SSH equivalent of
     * `php artisan passport:keys`. The OAuth/MCP flow throws "Invalid key
     * supplied" until these exist. Will not overwrite existing keys unless
     * explicitly forced, since regenerating keys invalidates all live tokens.
     */
    public function generatePassportKeys(Request $request)
    {
        $force = $request->boolean('force');

        // Resolve the exact paths Passport reads from at runtime, so generated
        // keys land where the auth server expects them.
        $publicKey = \Laravel\Passport\Passport::keyPath('oauth-public.key');
        $privateKey = \Laravel\Passport\Passport::keyPath('oauth-private.key');

        if ((file_exists($publicKey) || file_exists($privateKey)) && ! $force) {
            return back()->with('error', 'Passport keys already exist. Tick "overwrite" only if you intend to regenerate them — doing so invalidates every issued token.');
        }

        // Generate directly rather than calling the passport:keys artisan
        // command: Passport only registers that command when runningInConsole(),
        // so it does not exist inside a web request. This mirrors KeysCommand.
        try {
            @set_time_limit(120); // 4096-bit RSA generation can be slow on shared hosts.

            $key = \phpseclib4\Crypt\RSA::createKey(4096);

            if (file_put_contents($publicKey, (string) $key->getPublicKey()) === false
                || file_put_contents($privateKey, (string) $key) === false) {
                return back()->with('error', 'Could not write key files to storage/. Check that storage/ is writable (775).');
            }

            if (! windows_os()) {
                @chmod($publicKey, 0660);
                @chmod($privateKey, 0600);
            }

            return back()->with('success', 'Passport keys generated in storage/. Keep them out of version control (.gitignore already excludes /storage/*.key).');
        } catch (\Throwable $e) {
            return back()->with('error', 'Key generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Create the public (PKCE, no-secret) MCP OAuth client from the browser —
     * the no-SSH equivalent of `php artisan mcp:create-client`. Prints the
     * Client ID to paste into OpenAI's connector. Refuses to create a second
     * client if one named mcp_client already exists.
     */
    public function createMcpClient(Request $request)
    {
        $request->validate([
            'redirect_uri' => 'required|url',
        ]);

        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('oauth_clients')) {
                return back()->with('error', 'The oauth_clients table does not exist yet — run migrations first.');
            }

            $exists = \Illuminate\Support\Facades\DB::table('oauth_clients')->where('name', 'mcp_client')->exists();
            if ($exists) {
                return back()->with('error', 'An "mcp_client" already exists. Delete it first if you need to recreate it.');
            }

            $clients = app(\Laravel\Passport\ClientRepository::class);
            $client = $clients->createAuthorizationCodeGrantClient(
                name: 'mcp_client',
                redirectUris: [trim($request->input('redirect_uri'))],
                confidential: false,
            );

            return back()->with('success', 'MCP client created. Client ID: ' . $client->getKey() . ' — configure OpenAI\'s connector with this ID (public client, no secret, PKCE required).');
        } catch (\Throwable $e) {
            return back()->with('error', 'Client creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Reset the MCP client: delete the existing mcp_client (and any tokens
     * issued to it) and create a fresh one. Use this when the Client ID has
     * been lost and needs regenerating. Guarded by a confirmation checkbox
     * because it invalidates the old client — OpenAI's connector must be
     * reconfigured with the new Client ID afterwards.
     */
    public function resetMcpClient(Request $request)
    {
        $request->validate([
            'redirect_uri' => 'required|url',
            'confirm'      => 'accepted',
        ]);

        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('oauth_clients')) {
                return back()->with('error', 'The oauth_clients table does not exist yet — run migrations first.');
            }

            $clientIds = \Illuminate\Support\Facades\DB::table('oauth_clients')
                ->where('name', 'mcp_client')
                ->pluck('id');

            \Illuminate\Support\Facades\DB::transaction(function () use ($clientIds) {
                if ($clientIds->isNotEmpty()) {
                    // Revoke/remove tokens tied to the old client(s) first.
                    if (\Illuminate\Support\Facades\Schema::hasTable('oauth_access_tokens')) {
                        \Illuminate\Support\Facades\DB::table('oauth_access_tokens')
                            ->whereIn('client_id', $clientIds)->delete();
                    }
                    if (\Illuminate\Support\Facades\Schema::hasTable('oauth_auth_codes')) {
                        \Illuminate\Support\Facades\DB::table('oauth_auth_codes')
                            ->whereIn('client_id', $clientIds)->delete();
                    }
                    \Illuminate\Support\Facades\DB::table('oauth_clients')
                        ->whereIn('id', $clientIds)->delete();
                }
            });

            $clients = app(\Laravel\Passport\ClientRepository::class);
            $client = $clients->createAuthorizationCodeGrantClient(
                name: 'mcp_client',
                redirectUris: [trim($request->input('redirect_uri'))],
                confidential: false,
            );

            return back()->with('success', 'MCP client reset. New Client ID: ' . $client->getKey() . ' — reconfigure OpenAI\'s connector with this ID. The previous client and its tokens were removed.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Client reset failed: ' . $e->getMessage());
        }
    }

    /**
     * Reconcile the migrations history for a database whose tables already
     * exist but whose `migrations` table doesn't record them (e.g. after a DB
     * copy/restore). For each pending "create_<table>_table" migration whose
     * table is already present, we INSERT a history record so Laravel stops
     * treating it as pending — WITHOUT running any schema SQL.
     *
     * This is strictly non-destructive: it only inserts rows into the
     * `migrations` table. It never creates, alters or drops any application
     * table, so existing data cannot be touched.
     */
    public function syncMigrationHistory(Request $request)
    {
        $request->validate([
            'confirm' => 'accepted',
        ]);

        try {
            $migrator = app('migrator');
            $repository = $migrator->getRepository();

            if (! $repository->repositoryExists()) {
                $repository->createRepository();
            }

            $ran = $repository->getRan();
            $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));

            $pending = array_filter($files, fn ($name) => ! in_array($name, $ran, true));

            // Next batch number, so these records group together and can be
            // identified/rolled back as a unit if ever needed.
            $batch = $repository->getNextBatchNumber();

            $synced = [];
            foreach ($pending as $name) {
                if ($this->migrationTargetExists($name)) {
                    $repository->log($name, $batch);
                    $synced[] = $name;
                }
            }

            if (empty($synced)) {
                return back()->with('success', 'Nothing to sync — no pending create-table migrations have an existing table.');
            }

            return back()->with('success', count($synced) . ' migration(s) marked as already applied (no schema changes made): ' . implode(', ', $synced));
        } catch (\Throwable $e) {
            return back()->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Run a whitelisted maintenance action. Each maps to an artisan command
     * (or the storage:link rebuild) so nothing arbitrary can be executed.
     */
    public function runMaintenance(Request $request)
    {
        $request->validate([
            'action' => 'required|string|in:cache_clear,config_clear,config_cache,route_clear,route_cache,view_clear,storage_link,optimize_clear',
        ]);

        $action = $request->input('action');

        if ($action === 'storage_link') {
            return $this->rebuildStorageLink();
        }

        // "Clear all" runs the deploy-critical clears individually so that a
        // flaky cache store (e.g. a database cache that's briefly unreachable)
        // can't stop config/route/view from being cleared.
        if ($action === 'optimize_clear') {
            $results = [];
            foreach (['config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
                try {
                    \Illuminate\Support\Facades\Artisan::call($cmd);
                    $results[] = $cmd . ' ✓';
                } catch (\Throwable $e) {
                    $results[] = $cmd . ' ✗ (' . $e->getMessage() . ')';
                }
            }

            $failed = collect($results)->contains(fn ($r) => str_contains($r, '✗'));

            return back()->with(
                $failed ? 'error' : 'success',
                ($failed ? 'Some caches could not be cleared: ' : 'All caches cleared: ') . implode(', ', $results)
            );
        }

        $map = [
            'cache_clear'    => ['cache:clear',  'Application cache cleared.'],
            'config_clear'   => ['config:clear', 'Config cache cleared.'],
            'config_cache'   => ['config:cache', 'Config cached.'],
            'route_clear'    => ['route:clear',  'Route cache cleared.'],
            'route_cache'    => ['route:cache',  'Routes cached.'],
            'view_clear'     => ['view:clear',   'Compiled views cleared.'],
        ];

        [$command, $message] = $map[$action];

        try {
            \Illuminate\Support\Facades\Artisan::call($command);
            $output = trim(\Illuminate\Support\Facades\Artisan::output());

            return back()->with('success', $message . ($output !== '' ? ' ' . $output : ''));
        } catch (\Throwable $e) {
            return back()->with('error', "Failed to run '{$command}': " . $e->getMessage());
        }
    }

    /**
     * Rebuild the public/storage symlink. Falls back gracefully when the host
     * disallows symlink() — a common cPanel restriction — in which case the
     * PHP storage fallback route keeps images working anyway.
     */
    private function rebuildStorageLink()
    {
        $link = public_path('storage');
        $target = storage_path('app/public');

        // Already a valid symlink pointing at the right place — nothing to do.
        if (is_link($link) && realpath(readlink($link)) === realpath($target)) {
            return back()->with('success', 'Storage link already present and correct.');
        }

        // Remove a stale/broken link or an empty directory left by a deploy.
        if (is_link($link)) {
            @unlink($link);
        } elseif (is_dir($link) && count((array) @scandir($link)) <= 2) {
            @rmdir($link);
        } elseif (is_dir($link)) {
            return back()->with('error', 'public/storage exists as a non-empty folder. Remove it manually in File Manager, then retry.');
        }

        if (@symlink($target, $link)) {
            return back()->with('success', 'Storage link created successfully.');
        }

        return back()->with('error', 'Could not create the symlink (the host may block symlink()). Images will still load via the PHP storage fallback route, so no action is strictly required.');
    }
}
