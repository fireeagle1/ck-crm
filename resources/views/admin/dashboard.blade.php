<x-admin-layout>
    <x-slot:title>Admin Dashboard</x-slot:title>

    <h1 class="text-2xl font-semibold mb-6">Admin Dashboard</h1>

    {{-- KPI strip --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <p class="text-sm text-gray-500">Open Tickets</p>
            <p class="text-3xl font-semibold mt-1">{{ $openTickets }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <p class="text-sm text-gray-500">Active Services</p>
            <p class="text-3xl font-semibold mt-1">{{ $activeServices }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <p class="text-sm text-gray-500">Quick Links</p>
            <div class="mt-2 space-y-1">
                <a href="{{ route('admin.customers.create') }}" class="block text-sm text-blue-600 hover:underline">Add customer</a>
                <a href="{{ route('admin.users.create') }}" class="block text-sm text-blue-600 hover:underline">Add user</a>
            </div>
        </div>
    </div>

    {{-- Recent logins --}}
    <div class="bg-white rounded-lg shadow-sm border p-5">
        <h2 class="text-lg font-semibold mb-3">Recent Logins</h2>
        @if ($recentLogins->isEmpty())
            <p class="text-sm text-gray-600">No recent logins.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($recentLogins as $login)
                    <li class="py-2 flex items-center justify-between">
                        <span>{{ $login->full_name }}</span>
                        <span class="text-sm text-gray-500">{{ $login->last_login->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-admin-layout>
