<x-admin-layout>
    <x-slot:title>Users</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Users</h1>
        <a href="{{ route('admin.users.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
            Add User
        </a>
    </div>

    {{-- Search & Filter --}}
    <div class="mb-4 flex flex-wrap items-center gap-3" x-data="{ showDisabled: {{ request('show_disabled') ? 'true' : 'false' }} }">
        <form method="GET" action="{{ route('admin.users.index') }}" class="flex items-center gap-2 flex-1">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name or email..."
                   class="w-full max-w-sm rounded-md border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
            <input type="hidden" name="show_disabled" :value="showDisabled ? '1' : '0'">
            <button type="submit" class="px-4 py-2 bg-gray-100 border rounded-md text-sm font-medium hover:bg-gray-200">Search</button>
        </form>
        <button type="button"
                @click="showDisabled = !showDisabled; $nextTick(() => { $el.closest('.mb-4').querySelector('form').submit() })"
                :class="showDisabled ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-gray-100 text-gray-600 border-gray-300'"
                class="px-3 py-2 border rounded-md text-sm font-medium hover:bg-gray-200 transition">
            <span x-text="showDisabled ? '✓ Showing Disabled' : 'Show Disabled'"></span>
        </button>
    </div>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Name</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Email</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Company</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Admin</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Last Login</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($users as $user)
                    <tr class="hover:bg-gray-50 {{ $user->is_locked ? 'opacity-60' : '' }}">
                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('admin.users.edit', $user) }}" class="text-blue-600 hover:underline">{{ $user->full_name }}</a>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            @if ($user->customer)
                                <a href="{{ route('admin.customers.show', $user->customer) }}" class="text-blue-600 hover:underline">{{ $user->customer->company_name }}</a>
                            @else
                                <span class="text-gray-500">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->is_admin)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-purple-100 text-purple-700">Admin</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->is_locked)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700">Disabled</span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Active</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $user->last_login?->diffForHumans() ?? 'Never' }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center px-3 py-1.5 bg-blue-50 text-blue-700 rounded-md text-sm font-medium hover:bg-blue-100 transition">
                                Edit
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->appends(request()->query())->links() }}</div>
</x-admin-layout>
