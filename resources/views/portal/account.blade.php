<x-portal-layout>
    <x-slot:title>Account</x-slot:title>

    <h1 class="text-2xl font-semibold mb-4">Account Details</h1>

    <div class="bg-white rounded-lg shadow-sm border p-6 max-w-2xl">
        <form method="POST" action="{{ route('portal.account.update') }}">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
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

                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <p class="mt-1 text-sm text-gray-600">{{ $user->email }}</p>
                </div>

                <div>
                    <label for="phone_number" class="block text-sm font-medium text-gray-700">Phone</label>
                    <input type="text" name="phone_number" id="phone_number"
                           value="{{ old('phone_number', $user->phone_number) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                @if ($user->customer)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Company</label>
                        <p class="mt-1 text-sm text-gray-600">{{ $user->customer->company_name }}</p>
                    </div>
                @endif

                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</x-portal-layout>
