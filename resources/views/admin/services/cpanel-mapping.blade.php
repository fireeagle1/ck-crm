<x-admin-layout>
    <x-slot:title>cPanel Mapping</x-slot:title>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">cPanel Account Mapping</h1>
            <p class="text-sm text-gray-500 mt-1">Assign cPanel usernames to services so customers can single sign-on.</p>
        </div>
        <a href="{{ route('admin.services.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Services</a>
    </div>

    {{-- WHM Accounts available --}}
    @if (!empty($whmAccounts))
        <div class="bg-white rounded-lg border p-5 mb-6">
            <h2 class="font-bold mb-3">WHM Accounts on Server ({{ count($whmAccounts) }})</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 text-sm">
                @foreach ($whmAccounts as $acct)
                    <div class="px-3 py-2 rounded border {{ $acct['suspended'] ? 'bg-red-50 border-red-200' : 'bg-gray-50' }}">
                        <span class="font-mono font-medium">{{ $acct['user'] }}</span>
                        <span class="text-xs text-gray-400 ml-1">({{ $acct['domain'] }})</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Currently mapped services --}}
    <div class="bg-white rounded-lg border overflow-hidden mb-6">
        <div class="px-5 py-4 border-b">
            <h2 class="font-bold">Mapped Services ({{ $services->count() }})</h2>
        </div>
        <form method="POST" action="{{ route('admin.services.cpanel-mapping.update') }}">
            @csrf
            @method('PUT')
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Service</th>
                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Customer</th>
                        <th class="px-5 py-3 text-left font-semibold text-gray-600">cPanel Username</th>
                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Domain</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($services as $i => $service)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <input type="hidden" name="mappings[{{ $i }}][service_id]" value="{{ $service->service_id }}">
                                <a href="{{ route('admin.services.show', $service) }}" class="text-blue-600 hover:underline font-medium">{{ $service->service_short }}</a>
                            </td>
                            <td class="px-5 py-3 text-gray-600">{{ $service->customer?->company_name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <input type="text" name="mappings[{{ $i }}][cpanel_username]"
                                       value="{{ $service->cpanel_username }}"
                                       class="w-40 rounded border-gray-300 text-sm px-2 py-1 font-mono focus:ring-blue-500 focus:border-blue-500"
                                       list="whm-accounts">
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ $service->domain_name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($services->isNotEmpty())
                <div class="px-5 py-3 border-t bg-gray-50">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700">
                        Save Mappings
                    </button>
                </div>
            @endif
        </form>
    </div>

    {{-- Unmapped hosting services --}}
    @if ($unmapped->isNotEmpty())
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-5">
            <h2 class="font-bold text-amber-900 mb-2">Unmapped Hosting Services ({{ $unmapped->count() }})</h2>
            <p class="text-sm text-amber-800 mb-3">These services are marked as Web Hosting but don't have a cPanel username assigned.</p>
            <ul class="space-y-1">
                @foreach ($unmapped as $service)
                    <li class="text-sm">
                        <a href="{{ route('admin.services.edit', $service) }}" class="text-blue-600 hover:underline">
                            {{ $service->service_short }}
                        </a>
                        <span class="text-gray-500">— {{ $service->customer?->company_name }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Datalist for autocomplete --}}
    @if (!empty($whmAccounts))
        <datalist id="whm-accounts">
            @foreach ($whmAccounts as $acct)
                <option value="{{ $acct['user'] }}">{{ $acct['domain'] }}</option>
            @endforeach
        </datalist>
    @endif
</x-admin-layout>
