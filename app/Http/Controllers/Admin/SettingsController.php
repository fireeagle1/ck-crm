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
        ];

        return view('admin.settings.general', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'site_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        Setting::set('site_name', $request->input('site_name'));

        if ($request->hasFile('logo')) {
            $oldPath = Setting::get('logo_path');
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('logo')->store('branding', 'public');
            Setting::set('logo_path', $path);
        }

        return back()->with('success', 'Settings saved.');
    }

    public function deleteLogo()
    {
        $path = Setting::get('logo_path');

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
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

        \Illuminate\Support\Facades\Artisan::call($command);
        $output = \Illuminate\Support\Facades\Artisan::output();

        return back()->with('success', "Ran '{$command}'. Output: " . trim($output));
    }
}
