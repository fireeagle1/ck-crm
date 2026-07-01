<x-admin-layout>
    <x-slot:title>Stripe Mapping</x-slot:title>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Stripe Customer Mapping</h1>
            <p class="text-sm text-gray-500 mt-1">Link your CRM customers to their Stripe customer accounts. This controls which invoices/subscriptions sync to each customer.</p>
        </div>
        <a href="{{ route('admin.services.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Services</a>
    </div>

    {{-- Unmapped customers --}}
    @if ($unmapped->isNotEmpty())
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-5 mb-6">
            <h2 class="font-bold text-amber-900 mb-2">Unmapped Customers ({{ $unmapped->count() }})</h2>
            <p class="text-sm text-amber-800 mb-3">These customers don't have a Stripe ID. Their invoices won't sync until mapped.</p>
            <form method="POST" action="{{ route('admin.services.stripe-mapping.update') }}">
                @csrf
                @method('PUT')
                <div class="space-y-2">
                    @foreach ($unmapped as $i => $customer)
                        <div class="flex items-center gap-3 bg-white rounded border px-3 py-2">
                            <input type="hidden" name="mappings[{{ $i }}][company_id]" value="{{ $customer->company_id }}">
                            <span class="text-sm font-medium w-48 truncate">{{ $customer->company_name ?: $customer->customer_name }}</span>
                            <input type="text" name="mappings[{{ $i }}][stripe_customer_id]"
                                   placeholder="cus_..."
                                   class="flex-1 rounded border-gray-300 text-sm px-2 py-1 font-mono focus:ring-blue-500 focus:border-blue-500"
                                   list="stripe-customers">
                        </div>
                    @endforeach
                </div>
                <button type="submit" class="mt-3 px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700">
                    Save Mappings
                </button>
            </form>
        </div>
    @endif

    {{-- Currently mapped --}}
    <div class="bg-white rounded-lg border overflow-hidden">
        <div class="px-5 py-4 border-b">
            <h2 class="font-bold">Mapped Customers ({{ $mapped->count() }})</h2>
        </div>
        <form method="POST" action="{{ route('admin.services.stripe-mapping.update') }}">
            @csrf
            @method('PUT')
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Customer</th>
                        <th class="px-5 py-3 text-left font-semibold text-gray-600">Stripe Customer ID</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($mapped as $i => $customer)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <input type="hidden" name="mappings[{{ $i }}][company_id]" value="{{ $customer->company_id }}">
                                <a href="{{ route('admin.customers.show', $customer) }}" class="text-blue-600 hover:underline font-medium">
                                    {{ $customer->company_name ?: $customer->customer_name }}
                                </a>
                            </td>
                            <td class="px-5 py-3">
                                <input type="text" name="mappings[{{ $i }}][stripe_customer_id]"
                                       value="{{ $customer->stripe_customer_id }}"
                                       class="w-64 rounded border-gray-300 text-sm px-2 py-1 font-mono focus:ring-blue-500 focus:border-blue-500"
                                       list="stripe-customers">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($mapped->isNotEmpty())
                <div class="px-5 py-3 border-t bg-gray-50">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700">
                        Save Changes
                    </button>
                </div>
            @endif
        </form>
    </div>

    {{-- Stripe customers datalist for autocomplete --}}
    @if (!empty($stripeCustomers))
        <datalist id="stripe-customers">
            @foreach ($stripeCustomers as $sc)
                <option value="{{ $sc['id'] }}">{{ $sc['name'] }} ({{ $sc['email'] }})</option>
            @endforeach
        </datalist>

        <div class="mt-6 bg-white rounded-lg border p-5">
            <h2 class="font-bold mb-3">Stripe Customers ({{ count($stripeCustomers) }})</h2>
            <p class="text-xs text-gray-500 mb-3">Reference list from Stripe. Copy an ID to paste into the fields above.</p>
            <div class="max-h-64 overflow-y-auto">
                <table class="min-w-full text-xs">
                    <thead class="border-b sticky top-0 bg-white">
                        <tr>
                            <th class="px-3 py-1 text-left font-semibold">ID</th>
                            <th class="px-3 py-1 text-left font-semibold">Name</th>
                            <th class="px-3 py-1 text-left font-semibold">Email</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($stripeCustomers as $sc)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-1.5 font-mono">{{ $sc['id'] }}</td>
                                <td class="px-3 py-1.5">{{ $sc['name'] }}</td>
                                <td class="px-3 py-1.5 text-gray-500">{{ $sc['email'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-admin-layout>
