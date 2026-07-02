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
        ];

        return view('admin.settings.general', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'site_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'logo_dark' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'favicon' => 'nullable|mimes:ico,png,svg|max:512',
        ]);

        Setting::set('site_name', $request->input('site_name'));

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
}
