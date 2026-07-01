<x-admin-layout>
    <x-slot:title>Users</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Users</h1>
        <a href="{{ route('admin.users.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
            Add User
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Name</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Email</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Company</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Admin</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Last Login</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($users as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ $user->full_name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $user->email }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $user->customer?->company_name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($user->is_admin)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-purple-100 text-purple-700">Admin</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $user->last_login?->diffForHumans() ?? 'Never' }}</td>
                        <td class="px-4 py-3">
                            @if (!$user->is_admin)
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('admin.users.impersonate', $user) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-blue-600 hover:underline text-sm">Impersonate</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" class="inline"
                                          x-data="{ show: false }">
                                        @csrf
                                        <button type="button" @click="show = !show" class="text-amber-600 hover:underline text-sm">Reset PW</button>
                                        <div x-show="show" x-transition class="mt-1 flex gap-1">
                                            <input type="password" name="new_password" placeholder="New password" required minlength="8"
                                                   class="w-32 rounded border-gray-300 text-xs px-2 py-1">
                                            <button type="submit" class="px-2 py-1 bg-amber-600 text-white rounded text-xs">Set</button>
                                        </div>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No users.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</x-admin-layout>
