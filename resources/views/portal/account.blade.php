<x-portal-layout>
    <x-slot:title>Account</x-slot:title>

    <h1 class="text-3xl font-bold tracking-tight mb-6">Account Settings</h1>

    <div class="space-y-6 max-w-3xl">
        {{-- Personal details --}}
        <div class="bg-white rounded-lg border p-6">
            <h2 class="font-bold mb-4">Your Details</h2>
            <form method="POST" action="{{ route('portal.account.update') }}">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                        <input type="text" name="first_name" id="first_name" required
                               value="{{ old('first_name', $user->first_name) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                        <input type="text" name="last_name" id="last_name" required
                               value="{{ old('last_name', $user->last_name) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <p class="mt-1 text-sm text-gray-600 py-2">{{ $user->email }}</p>
                    </div>
                    <div>
                        <label for="phone_number" class="block text-sm font-medium text-gray-700">Phone</label>
                        <input type="text" name="phone_number" id="phone_number"
                               value="{{ old('phone_number', $user->phone_number) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700">Save</button>
            </form>
        </div>

        {{-- Company details --}}
        @if ($user->customer)
            <div class="bg-white rounded-lg border p-6">
                <h2 class="font-bold mb-4">Company Details</h2>
                <form method="POST" action="{{ route('portal.account.company.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Company Name</label>
                        <p class="mt-1 text-sm text-gray-600 py-2 font-medium">{{ $user->customer->company_name }}</p>
                        <p class="text-xs text-gray-400">Company name cannot be changed here. Contact support if this needs updating.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="phone_number_co" class="block text-sm font-medium text-gray-700">Company Phone</label>
                            <input type="text" name="phone_number" id="phone_number_co"
                                   value="{{ old('phone_number', $user->customer->phone_number) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="address_line1" class="block text-sm font-medium text-gray-700">Address Line 1</label>
                            <input type="text" name="address_line1" id="address_line1"
                                   value="{{ old('address_line1', $user->customer->address_line1) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="address_line2" class="block text-sm font-medium text-gray-700">Address Line 2</label>
                            <input type="text" name="address_line2" id="address_line2"
                                   value="{{ old('address_line2', $user->customer->address_line2) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700">City</label>
                            <input type="text" name="city" id="city"
                                   value="{{ old('city', $user->customer->city) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <label for="state" class="block text-sm font-medium text-gray-700">County</label>
                            <input type="text" name="state" id="state"
                                   value="{{ old('state', $user->customer->state) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="postal_code" class="block text-sm font-medium text-gray-700">Postcode</label>
                            <input type="text" name="postal_code" id="postal_code"
                                   value="{{ old('postal_code', $user->customer->postal_code) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="country" class="block text-sm font-medium text-gray-700">Country</label>
                            <input type="text" name="country" id="country"
                                   value="{{ old('country', $user->customer->country) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700">Save Company Details</button>
                </form>
            </div>
        @endif

        {{-- Team / Users --}}
        <div class="bg-white rounded-lg border p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold">Team Members</h2>
            </div>

            {{-- Existing users --}}
            <div class="mb-6">
                <table class="min-w-full text-sm">
                    <thead class="border-b">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600">Name</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600">Email</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600">Last Login</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($companyUsers as $member)
                            <tr>
                                <td class="px-3 py-2 font-medium">
                                    {{ $member->full_name }}
                                    @if ($member->id === $user->id)
                                        <span class="text-xs text-blue-600">(you)</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-500">{{ $member->email }}</td>
                                <td class="px-3 py-2 text-gray-400">{{ $member->last_login?->diffForHumans() ?? 'Never' }}</td>
                                <td class="px-3 py-2">
                                    @if ($member->id !== $user->id)
                                        <form method="POST" action="{{ route('portal.account.users.reset-password', $member) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-blue-600 hover:underline text-xs">Send Password Reset</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Add new user --}}
            <div class="border-t pt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Invite New Team Member</h3>
                <form method="POST" action="{{ route('portal.account.users.add') }}">
                    @csrf
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <input type="text" name="first_name" required placeholder="First name"
                                   class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('first_name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <input type="text" name="last_name" required placeholder="Last name"
                                   class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('last_name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <input type="email" name="email" required placeholder="Email address"
                                   class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                            @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <button type="submit" class="mt-3 px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700">
                        Send Invite
                    </button>
                    <p class="text-xs text-gray-400 mt-2">They'll receive an email with login credentials.</p>
                </form>
            </div>
        </div>
    </div>
</x-portal-layout>
