<x-admin-layout>
    <x-slot:title>Search: {{ $q }}</x-slot:title>

    <h1 class="text-2xl font-semibold mb-4">Search results for "{{ $q }}"</h1>

    @if (empty($results))
        <p class="text-gray-500">Enter at least 2 characters to search.</p>
    @else
        @php $totalResults = collect($results)->flatten()->count(); @endphp

        @if ($totalResults === 0)
            <p class="text-gray-500">No results found for "{{ $q }}".</p>
        @else
            <p class="text-sm text-gray-500 mb-4">{{ $totalResults }} result(s) found.</p>
        @endif

        {{-- Customers --}}
        @if (!empty($results['customers']) && $results['customers']->isNotEmpty())
            <div class="bg-white rounded-lg shadow-sm border mb-4">
                <div class="px-5 py-3 border-b">
                    <h2 class="text-sm font-semibold text-gray-700">Customers ({{ $results['customers']->count() }})</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach ($results['customers'] as $customer)
                        <div class="px-5 py-3">
                            <a href="{{ route('admin.customers.show', $customer) }}" class="text-blue-600 hover:underline font-medium">
                                {{ $customer->company_name }}
                            </a>
                            @if ($customer->customer_name)
                                <span class="text-sm text-gray-500 ml-2">{{ $customer->customer_name }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Tickets --}}
        @if (!empty($results['tickets']) && $results['tickets']->isNotEmpty())
            <div class="bg-white rounded-lg shadow-sm border mb-4">
                <div class="px-5 py-3 border-b">
                    <h2 class="text-sm font-semibold text-gray-700">Tickets ({{ $results['tickets']->count() }})</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach ($results['tickets'] as $ticket)
                        <div class="px-5 py-3 flex items-center justify-between">
                            <div>
                                <a href="{{ route('admin.tickets.show', $ticket) }}" class="text-blue-600 hover:underline font-medium">
                                    INC{{ $ticket->ticket_id }}
                                </a>
                                <span class="text-sm text-gray-700 ml-2">{{ $ticket->subject }}</span>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $ticket->status === 'Open' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                                {{ $ticket->status }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Services --}}
        @if (!empty($results['services']) && $results['services']->isNotEmpty())
            <div class="bg-white rounded-lg shadow-sm border mb-4">
                <div class="px-5 py-3 border-b">
                    <h2 class="text-sm font-semibold text-gray-700">Services ({{ $results['services']->count() }})</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach ($results['services'] as $service)
                        <div class="px-5 py-3">
                            <span class="font-medium">{{ $service->service_short }}</span>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ml-2
                                {{ $service->status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                                {{ $service->status }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Domains --}}
        @if (!empty($results['domains']) && $results['domains']->isNotEmpty())
            <div class="bg-white rounded-lg shadow-sm border mb-4">
                <div class="px-5 py-3 border-b">
                    <h2 class="text-sm font-semibold text-gray-700">Domains ({{ $results['domains']->count() }})</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach ($results['domains'] as $domain)
                        <div class="px-5 py-3">
                            <a href="{{ route('admin.domains.edit', $domain) }}" class="text-blue-600 hover:underline font-medium">
                                {{ $domain->domain_name }}
                            </a>
                            <span class="text-sm text-gray-500 ml-2">{{ $domain->expiry_date?->format('Y-m-d') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Users --}}
        @if (!empty($results['users']) && $results['users']->isNotEmpty())
            <div class="bg-white rounded-lg shadow-sm border mb-4">
                <div class="px-5 py-3 border-b">
                    <h2 class="text-sm font-semibold text-gray-700">Users ({{ $results['users']->count() }})</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach ($results['users'] as $user)
                        <div class="px-5 py-3">
                            <span class="font-medium">{{ $user->full_name }}</span>
                            <span class="text-sm text-gray-500 ml-2">{{ $user->email }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</x-admin-layout>
