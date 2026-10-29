<x-admin-layout>
    <x-slot:title>MCP Tokens</x-slot:title>

    <h1 class="text-2xl font-bold mb-2">ChatGPT MCP Tokens</h1>
    <p class="text-gray-500 mb-6">
        OAuth access tokens issued to the ChatGPT MCP connector. Token values are never stored or shown &mdash;
        only metadata. Revoking a token immediately blocks all further calls using it; the connector must
        re-authorize to regain access.
    </p>

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
</x-admin-layout>
