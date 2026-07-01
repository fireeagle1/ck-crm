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
                                <form method="POST" action="{{ route('admin.users.impersonate', $user) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-blue-600 hover:underline text-sm">Impersonate</button>
                                </form>
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
