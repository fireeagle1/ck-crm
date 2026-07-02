<x-admin-layout>
    <x-slot:title>Edit User — {{ $user->full_name }}</x-slot:title>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold">Edit User</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $user->full_name }} &mdash; {{ $user->email }}</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Back to Users</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Edit Form --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- User Details --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-semibold mb-4">User Details</h2>
                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                    @csrf
                    @method('PUT')

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
                                <input type="text" name="first_name" id="first_name" required
                                       value="{{ old('first_name', $user->first_name) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                @error('first_name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" name="last_name" id="last_name" required
                                       value="{{ old('last_name', $user->last_name) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                @error('last_name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" required
                                   value="{{ old('email', $user->email) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('email') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="company_id" class="block text-sm font-medium text-gray-700">Company <span class="text-red-500">*</span></label>
                            <select name="company_id" id="company_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select company...</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->company_id }}" {{ old('company_id', $user->company_id) == $customer->company_id ? 'selected' : '' }}>
                                        {{ $customer->company_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('company_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="phone_number" class="block text-sm font-medium text-gray-700">Phone</label>
                            <input type="text" name="phone_number" id="phone_number"
                                   value="{{ old('phone_number', $user->phone_number) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="hidden" name="is_admin" value="0">
                            <input type="checkbox" name="is_admin" id="is_admin" value="1"
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                   {{ old('is_admin', $user->is_admin) ? 'checked' : '' }}>
                            <label for="is_admin" class="text-sm text-gray-700">Grant admin access</label>
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Reset Password --}}
            <div class="bg-white rounded-lg shadow-sm border p-6" x-data="{ open: false }">
                <h2 class="text-lg font-semibold mb-4">Reset Password</h2>
                <p class="text-sm text-gray-500 mb-4">Set a new password for this user. They will need to use the new password on their next login.</p>
                <button type="button" @click="open = !open"
                        class="px-4 py-2 bg-amber-50 text-amber-700 border border-amber-200 rounded-md text-sm font-medium hover:bg-amber-100 transition">
                    Reset Password
                </button>
                <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" x-show="open" x-transition class="mt-4">
                    @csrf
                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                            <input type="password" name="new_password" id="new_password" required minlength="8"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Minimum 8 characters">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-md text-sm font-medium hover:bg-amber-700">
                            Set Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Status & Actions --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-semibold mb-4">Account Status</h2>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Status</span>
                        @if ($user->is_locked)
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-red-100 text-red-700">Disabled</span>
                        @else
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-green-100 text-green-700">Active</span>
                        @endif
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Role</span>
                        @if ($user->is_admin)
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-purple-100 text-purple-700">Admin</span>
                        @else
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-gray-100 text-gray-700">User</span>
                        @endif
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Failed Attempts</span>
                        <span class="text-sm font-medium {{ $user->failed_attempts > 0 ? 'text-amber-600' : 'text-gray-900' }}">{{ $user->failed_attempts ?? 0 }}</span>
                    </div>

                    <hr class="border-gray-200">

                    {{-- Toggle Lock --}}
                    <form method="POST" action="{{ route('admin.users.toggle-lock', $user) }}">
                        @csrf
                        @if ($user->is_locked)
                            <button type="submit" class="w-full px-4 py-2 bg-green-50 text-green-700 border border-green-200 rounded-md text-sm font-medium hover:bg-green-100 transition">
                                Enable Account
                            </button>
                        @else
                            <button type="submit" class="w-full px-4 py-2 bg-red-50 text-red-700 border border-red-200 rounded-md text-sm font-medium hover:bg-red-100 transition"
                                    onclick="return confirm('Are you sure you want to disable this user? They will not be able to log in.')">
                                Disable Account
                            </button>
                        @endif
                    </form>

                    {{-- Impersonate --}}
                    @if (!$user->is_admin)
                        <form method="POST" action="{{ route('admin.users.impersonate', $user) }}">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 bg-blue-50 text-blue-700 border border-blue-200 rounded-md text-sm font-medium hover:bg-blue-100 transition">
                                Impersonate User
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Login History --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-semibold mb-4">Login History</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Last Login</span>
                        <span class="text-gray-900 font-medium">{{ $user->last_login?->format('d M Y H:i') ?? 'Never' }}</span>
                    </div>
                    @if ($user->last_login)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600"></span>
                            <span class="text-gray-500 text-xs">{{ $user->last_login->diffForHumans() }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Last Failed Login</span>
                        <span class="text-gray-900 font-medium">{{ $user->last_failed_login?->format('d M Y H:i') ?? 'Never' }}</span>
                    </div>
                    @if ($user->lock_until)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Locked Until</span>
                            <span class="text-red-600 font-medium">{{ $user->lock_until->format('d M Y H:i') }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Account Created</span>
                        <span class="text-gray-900 font-medium">{{ $user->created_at?->format('d M Y') ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
