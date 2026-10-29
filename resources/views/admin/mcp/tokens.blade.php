<x-admin-layout>
    <x-slot:title>MCP Tokens</x-slot:title>

    @php
        $settingsNav = [
            ['route' => 'admin.settings.general', 'label' => 'General', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
            ['route' => 'admin.settings.tasks', 'label' => 'Scheduled Tasks', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['route' => 'admin.settings.maintenance', 'label' => 'Maintenance', 'icon' => 'M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.53 1.53 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z'],
            ['route' => 'admin.mcp.tokens.index', 'label' => 'MCP Tokens', 'icon' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z'],
            ['route' => 'admin.mcp.audit.index', 'label' => 'MCP Audit Log', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
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
                            <a href="{{ \Route::has($item['route']) ? route($item['route']) : '#' }}"
                               class="flex items-center gap-3 px-4 py-2.5 text-sm transition
                                      {{ request()->routeIs($item['route'] . '*') ? 'bg-blue-50 text-blue-700 font-medium border-r-2 border-blue-600' : 'text-gray-700 hover:bg-gray-50' }}">
                                <svg class="w-4 h-4 shrink-0 {{ request()->routeIs($item['route'] . '*') ? 'text-blue-600' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
            <h1 class="text-2xl font-bold mb-2">ChatGPT MCP Tokens</h1>
            <p class="text-gray-500 mb-6">
                OAuth access tokens issued to the ChatGPT MCP connector. Token values are never stored or shown &mdash;
                only metadata. Revoking a token immediately blocks all further calls using it; the connector must
                re-authorize to regain access.
            </p>

            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white rounded-lg border">
                <div class="px-5 py-4 border-b">
                    <h2 class="font-bold">Issued Tokens ({{ $tokens->count() }})</h2>
                </div>

                @if ($tokens->isEmpty())
                    <div class="px-5 py-8 text-center text-gray-500 text-sm">
                        No MCP tokens have been issued yet.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Token ID</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Client</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Scopes</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Created</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Last Used</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Expires</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-600">Status</th>
                                    <th class="px-4 py-2 text-right font-semibold text-gray-600">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach ($tokens as $token)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 font-mono text-xs text-gray-500" title="{{ $token['id'] }}">
                                            {{ \Illuminate\Support\Str::limit($token['id'], 16) }}
                                        </td>
                                        <td class="px-4 py-2">{{ $token['client'] }}</td>
                                        <td class="px-4 py-2">
                                            @forelse ($token['scopes'] as $scope)
                                                <span class="inline-block bg-gray-100 text-gray-700 rounded px-2 py-0.5 text-xs mr-1 mb-1">{{ $scope }}</span>
                                            @empty
                                                <span class="text-gray-400">—</span>
                                            @endforelse
                                        </td>
                                        <td class="px-4 py-2 text-gray-500">{{ $token['created_at']?->format('Y-m-d H:i') ?? '—' }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $token['last_used']?->format('Y-m-d H:i') ?? '—' }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $token['expires_at']?->format('Y-m-d H:i') ?? '—' }}</td>
                                        <td class="px-4 py-2">
                                            @if ($token['revoked'])
                                                <span class="inline-block bg-red-100 text-red-700 rounded px-2 py-0.5 text-xs">Revoked</span>
                                            @elseif ($token['expires_at'] && $token['expires_at']->isPast())
                                                <span class="inline-block bg-gray-200 text-gray-600 rounded px-2 py-0.5 text-xs">Expired</span>
                                            @else
                                                <span class="inline-block bg-green-100 text-green-700 rounded px-2 py-0.5 text-xs">Active</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            @unless ($token['revoked'])
                                                <form method="POST"
                                                      action="{{ route('admin.mcp.tokens.revoke', $token['id']) }}"
                                                      onsubmit="return confirm('Revoke this token? The connector will lose access until it re-authorizes.')">
                                                    @csrf
                                                    <button type="submit"
                                                            class="px-3 py-1 bg-red-600 text-white rounded-md text-xs font-semibold hover:bg-red-700">
                                                        Revoke
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-gray-400 text-xs">—</span>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
