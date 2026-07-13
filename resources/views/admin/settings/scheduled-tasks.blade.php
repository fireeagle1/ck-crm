<x-admin-layout>
    <x-slot:title>Scheduled Tasks</x-slot:title>

    @php
        $settingsNav = [
            ['route' => 'admin.settings.general', 'label' => 'General', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
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
            <h1 class="text-2xl font-semibold mb-6">Scheduled Tasks</h1>

            {{-- Registered schedules --}}
            <div class="bg-white rounded-lg shadow-sm border p-5 mb-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Registered Jobs</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">Command</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-600">Schedule</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($events as $event)
                                <tr>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $event['command'] ?? $event['description'] ?? 'Closure' }}</td>
                                    <td class="px-3 py-2 font-mono text-xs text-gray-500">{{ $event['expression'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-400 mt-3">These jobs are triggered by the cron: <code class="bg-gray-100 px-1 py-0.5 rounded">* * * * * cd /path && php artisan schedule:run</code></p>
            </div>

            {{-- Run on demand --}}
            <div class="bg-white rounded-lg shadow-sm border p-5 mb-6">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Run on Demand</h2>
                <p class="text-xs text-gray-500 mb-3">Manually trigger a sync immediately without waiting for the schedule.</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($runnableCommands as $cmd => $label)
                        <form method="POST" action="{{ route('admin.settings.tasks.run') }}" class="inline">
                            @csrf
                            <input type="hidden" name="command" value="{{ $cmd }}">
                            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $label }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>

            {{-- Task log --}}
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <div class="px-5 py-3 border-b flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700">Execution Log (last 30 days)</h2>
                    @if ($logs->isEmpty())
                        <span class="text-xs text-amber-600 font-medium">No logs yet — cron may not be running</span>
                    @endif
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Task</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Output</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Duration</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Ran At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($logs as $logEntry)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-mono text-xs">{{ $logEntry->task_name }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                        {{ $logEntry->status === 'completed' ? 'bg-green-100 text-green-700' : ($logEntry->status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                        {{ ucfirst($logEntry->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 text-xs max-w-xs truncate" title="{{ $logEntry->output }}">
                                    {{ Str::limit($logEntry->output, 60) }}
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs">
                                    {{ $logEntry->duration_seconds !== null ? $logEntry->duration_seconds . 's' : '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs">
                                    {{ $logEntry->started_at?->format('M j H:i') ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                    No task logs recorded yet. Once your cron job is running, logs will appear here.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $logs->links() }}</div>
        </div>
    </div>
</x-admin-layout>
