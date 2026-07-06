<x-portal-layout>
    <x-slot:title>Account Settings</x-slot:title>

    <div class="max-w-5xl mx-auto" x-data="{ tab: 'profile' }">
        {{-- Page header --}}
        <div class="mb-8">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Account Settings</h1>
            <p class="mt-1 text-sm text-gray-500">Manage your personal details, company information, and team members.</p>
        </div>

        {{-- Tab navigation --}}
        <div class="border-b border-gray-200 mb-8">
            <nav class="flex gap-6" aria-label="Account settings tabs">
                <button @click="tab = 'profile'"
                        :class="tab === 'profile' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="pb-3 px-1 border-b-2 font-medium text-sm transition whitespace-nowrap">
                    <svg class="w-4 h-4 inline-block mr-1.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                    Profile
                </button>
                @if ($user->customer)
                <button @click="tab = 'company'"
                        :class="tab === 'company' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="pb-3 px-1 border-b-2 font-medium text-sm transition whitespace-nowrap">
                    <svg class="w-4 h-4 inline-block mr-1.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M3.75 3v18h16.5V3H3.75zm3 3.75h3v3h-3v-3zm6 0h3v3h-3v-3zm-6 6h3v3h-3v-3zm6 0h3v3h-3v-3z"/></svg>
                    Company
                </button>
                @endif
                <button @click="tab = 'team'"
                        :class="tab === 'team' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="pb-3 px-1 border-b-2 font-medium text-sm transition whitespace-nowrap">
                    <svg class="w-4 h-4 inline-block mr-1.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                    Team
                </button>
            </nav>
        </div>

        {{-- Profile tab --}}
        <div x-show="tab === 'profile'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50">
                    <h2 class="text-lg font-semibold text-gray-900">Personal Information</h2>
                    <p class="mt-0.5 text-sm text-gray-500">Update your personal details and contact information.</p>
                </div>

                <form method="POST" action="{{ route('portal.account.update') }}" class="p-6">
                    @csrf
                    @method('PUT')

                    {{-- Profile header --}}
                    <div class="flex items-center gap-4 mb-8 pb-6 border-b border-gray-100">
                        <div class="h-16 w-16 rounded-full bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center text-xl font-bold text-white shadow-sm">
                            {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-lg font-semibold text-gray-900">{{ $user->full_name }}</p>
                            <p class="text-sm text-gray-500">{{ $user->email }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" name="first_name" id="first_name"
                                   value="{{ old('first_name', $user->first_name) }}"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition text-sm">
                            @error('first_name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" name="last_name" id="last_name"
                                   value="{{ old('last_name', $user->last_name) }}"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition text-sm">
                            @error('last_name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                            <input type="email" value="{{ $user->email }}" disabled
                                   class="block w-full rounded-lg border-gray-200 bg-gray-50 text-gray-500 shadow-sm text-sm cursor-not-allowed">
                            <p class="text-xs text-gray-400 mt-1">Contact support to change your email address.</p>
                        </div>
                        <div>
                            <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                            <input type="text" name="phone_number" id="phone_number"
                                   value="{{ old('phone_number', $user->phone_number) }}"
                                   placeholder="e.g. 07700 900000"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition text-sm">
                        </div>
                    </div>

                    <div class="mt-8 pt-5 border-t border-gray-100 flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Company tab --}}
        @if ($user->customer)
        <div x-show="tab === 'company'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50">
                    <h2 class="text-lg font-semibold text-gray-900">Company Information</h2>
                    <p class="mt-0.5 text-sm text-gray-500">Manage your company's contact details and address.</p>
                </div>

                <form method="POST" action="{{ route('portal.account.company.update') }}" class="p-6">
                    @csrf
                    @method('PUT')

                    {{-- Company name display --}}
                    <div class="mb-6 pb-6 border-b border-gray-100">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-gray-100 flex items-center justify-center">
                                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M3.75 3v18h16.5V3H3.75zm3 3.75h3v3h-3v-3zm6 0h3v3h-3v-3zm-6 6h3v3h-3v-3zm6 0h3v3h-3v-3z"/></svg>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">{{ $user->customer->company_name }}</p>
                                <p class="text-xs text-gray-400">Contact support to change your company name.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                        <div class="sm:col-span-2">
                            <label for="phone_number_co" class="block text-sm font-medium text-gray-700 mb-1">Company Phone</label>
                            <input type="text" name="phone_number" id="phone_number_co"
                                   value="{{ old('phone_number', $user->customer->phone_number) }}"
                                   placeholder="e.g. 020 7946 0958"
                                   class="block w-full sm:max-w-xs rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition text-sm">
                        </div>
                    </div>

                    {{-- Address section --}}
                    <div class="mt-6 pt-6 border-t border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4">Address</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                            <div>
                                <label for="address_line1" class="block text-sm font-medium text-gray-700 mb-1">Address Line 1</label>
                                <input type="text" name="address_line1" id="address_line1"
                                       value="{{ old('address_line1', $user->customer->address_line1) }}"
                                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition text-sm">
                            </div>
                            <div>
                                <label for="address_line2" class="block text-sm font-medium text-gray-700 mb-1">Address Line 2</label>
                                <input type="text" name="address_line2" id="address_line2"
                                       value="{{ old('address_line2', $user->customer->address_line2) }}"
                                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition text-sm">
                            </div>
                            <div>
                                <label for="city" class="block text-sm font-medium text-gray-700 mb-1">City</label>
                                <input type="text" name="city" id="city"
                                       value="{{ old('city', $user->customer->city) }}"
                                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition text-sm">
                            </div>
                            <div>
                                <label for="state" class="block text-sm font-medium text-gray-700 mb-1">County</label>
                                <input type="text" name="state" id="state"
                                       value="{{ old('state', $user->customer->state) }}"
                                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition text-sm">
                            </div>
                            <div>
                                <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-1">Postcode</label>
                                <input type="text" name="postal_code" id="postal_code"
                                       value="{{ old('postal_code', $user->customer->postal_code) }}"
                                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition text-sm">
                            </div>
                            <div>
                                <label for="country" class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                                <input type="text" name="country" id="country"
                                       value="{{ old('country', $user->customer->country) }}"
                                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition text-sm">
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-5 border-t border-gray-100 flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            Save Company Details
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        {{-- Team tab --}}
        <div x-show="tab === 'team'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
            {{-- Team members list --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Team Members</h2>
                        <p class="mt-0.5 text-sm text-gray-500">People who have access to your account.</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                        {{ $companyUsers->count() }} {{ Str::plural('member', $companyUsers->count()) }}
                    </span>
                </div>

                <div class="divide-y divide-gray-100">
                    @foreach ($companyUsers as $member)
                        <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50/50 transition">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full bg-gradient-to-br from-gray-400 to-gray-600 flex items-center justify-center text-sm font-bold text-white shadow-sm">
                                    {{ strtoupper(substr($member->first_name, 0, 1)) }}{{ strtoupper(substr($member->last_name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ $member->full_name }}
                                        @if ($member->id === $user->id)
                                            <span class="ml-1.5 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">You</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500">{{ $member->email }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="text-xs text-gray-400 hidden sm:block">
                                    @if ($member->last_login)
                                        Last active {{ $member->last_login->diffForHumans() }}
                                    @else
                                        Never logged in
                                    @endif
                                </span>
                                @if ($member->id !== $user->id)
                                    <form method="POST" action="{{ route('portal.account.users.reset-password', $member) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md text-xs font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 hover:text-gray-800 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                                            Reset Password
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Invite new member --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mt-6">
                <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50">
                    <h2 class="text-lg font-semibold text-gray-900">Invite Team Member</h2>
                    <p class="mt-0.5 text-sm text-gray-500">Add a new person to your account. They'll receive an email with login credentials.</p>
                </div>

                <form method="POST" action="{{ route('portal.account.users.add') }}" class="p-6">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="invite_first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" name="first_name" id="invite_first_name" required placeholder="Jane"
                                   value="{{ old('first_name') }}"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition text-sm">
                            @error('first_name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="invite_last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" name="last_name" id="invite_last_name" required placeholder="Smith"
                                   value="{{ old('last_name') }}"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition text-sm">
                            @error('last_name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="invite_email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                            <input type="email" name="email" id="invite_email" required placeholder="jane@company.com"
                                   value="{{ old('email') }}"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition text-sm">
                            @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="mt-5 flex items-center gap-3">
                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            Send Invite
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-portal-layout>
