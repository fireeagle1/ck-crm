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

        return view('admin.settings.maintenance', compact('storageLink', 'migrations'));
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

            return [
                'ok' => true,
                'ran_count' => count($ran),
                'pending' => $pending,
                'note' => null,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'ran_count' => 0, 'pending' => [], 'note' => 'Could not read migration status: ' . $e->getMessage()];
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
