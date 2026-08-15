<x-admin-layout>
    <x-slot:title>Customer Tiers</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Customer Tiers</h1>
    </div>

    <p class="text-sm text-gray-500 mb-6">Manage customer tiers to control product visibility in the shop.</p>

    {{-- Create New Tier --}}
    <div class="bg-white rounded-lg border p-4 mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Create New Tier</h2>
        <form method="POST" action="{{ route('admin.shop.tiers.store') }}" class="flex items-end gap-3">
            @csrf
            <div class="flex-1">
                <label for="new-tier-name" class="block text-xs font-medium text-gray-600 mb-1">Tier Name</label>
                <input type="text" name="name" id="new-tier-name" required
                       class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                       placeholder="e.g. Premium, Enterprise">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition">
                Create Tier
            </button>
        </form>
        @error('name')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Tiers List --}}
    <div class="space-y-4">
        @forelse ($tiers as $tier)
            <div class="bg-white rounded-lg border p-4" x-data="{ editing: false }">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900" x-show="!editing">{{ $tier->name }}</h3>
                        <p class="text-xs text-gray-500" x-show="!editing">Slug: <code class="bg-gray-100 px-1 rounded">{{ $tier->slug }}</code></p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-blue-50 text-blue-700">
                            {{ $tier->customers_count }} {{ Str::plural('customer', $tier->customers_count) }}
                        </span>
                        <button @click="editing = !editing" class="text-blue-600 hover:underline text-sm font-medium" x-text="editing ? 'Cancel' : 'Edit'"></button>
                        <form method="POST" action="{{ route('admin.shop.tiers.destroy', $tier) }}" class="inline"
                              onsubmit="return confirm('Delete this tier? All customer assignments will be removed.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline text-sm font-medium">Delete</button>
                        </form>
                    </div>
                </div>

                {{-- Edit Form --}}
                <form method="POST" action="{{ route('admin.shop.tiers.update', $tier) }}" x-show="editing" x-cloak>
                    @csrf
                    @method('PUT')

                    <div class="space-y-4">
                        <div>
                            <label for="tier-name-{{ $tier->id }}" class="block text-xs font-medium text-gray-600 mb-1">Tier Name</label>
                            <input type="text" name="name" id="tier-name-{{ $tier->id }}" value="{{ $tier->name }}" required
                                   class="w-full max-w-sm rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Assigned Customers</label>
                            <p class="text-xs text-gray-400 mb-2">Select which customers belong to this tier.</p>
                            <input type="hidden" name="customers" value="">
                            <div class="max-h-48 overflow-y-auto border rounded-md p-2 space-y-1">
                                @foreach ($customers as $customer)
                                    <label class="flex items-center gap-2 text-sm text-gray-700 hover:bg-gray-50 px-2 py-1 rounded cursor-pointer">
                                        <input type="checkbox" name="customers[]" value="{{ $customer->company_id }}"
                                               {{ $tier->customers->contains('company_id', $customer->company_id) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        {{ $customer->company_name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition">
                                Save Changes
                            </button>
                            <button type="button" @click="editing = false" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-semibold hover:bg-gray-200 transition">
                                Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @empty
            <div class="bg-white rounded-lg border p-6 text-center text-gray-500">
                No customer tiers created yet. Create one above to start segmenting your customers.
            </div>
        @endforelse
    </div>
</x-admin-layout>
