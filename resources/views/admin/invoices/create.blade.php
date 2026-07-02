<x-admin-layout>
    <x-slot:title>Create Invoice</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Create One-Off Invoice</h1>
        <a href="{{ route('admin.invoices.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Invoices</a>
    </div>

    <div class="bg-white rounded-lg border p-6 max-w-3xl">
        <form method="POST" action="{{ route('admin.invoices.store') }}" x-data="invoiceForm()">
            @csrf

            <div class="space-y-5">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="company_id" class="block text-sm font-semibold text-gray-700">Customer <span class="text-red-500">*</span></label>
                        <select name="company_id" id="company_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select customer...</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->company_id }}" {{ old('company_id') == $customer->company_id ? 'selected' : '' }}>
                                    {{ $customer->company_name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Only customers with Stripe accounts are shown.</p>
                    </div>
                    <div>
                        <label for="days_until_due" class="block text-sm font-semibold text-gray-700">Payment Terms (days)</label>
                        <input type="number" name="days_until_due" id="days_until_due" min="1" max="90"
                               value="{{ old('days_until_due', 7) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                {{-- Line items --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Line Items</label>
                    <div class="space-y-2">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="flex gap-2 items-start">
                                <input type="text" x-model="item.description"
                                       :name="`items[${index}][description]`"
                                       placeholder="Description" required
                                       class="flex-1 rounded-md border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                                <div class="relative w-28">
                                    <span class="absolute left-3 top-2 text-gray-400 text-sm">£</span>
                                    <input type="number" x-model="item.amount"
                                           :name="`items[${index}][amount]`"
                                           placeholder="0.00" step="0.01" min="0.01" required
                                           class="w-full pl-7 rounded-md border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <button type="button" @click="items.splice(index, 1)" x-show="items.length > 1"
                                        class="px-2 py-2 text-red-500 hover:text-red-700 text-sm">✕</button>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="items.push({description:'', amount:''})"
                            class="mt-2 text-sm text-blue-600 hover:underline font-medium">+ Add line item</button>
                </div>

                {{-- Total --}}
                <div class="bg-gray-50 rounded-md p-3 flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-700">Total</span>
                    <span class="text-lg font-bold" x-text="'£' + items.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0).toFixed(2)"></span>
                </div>

                <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700"
                        onclick="return confirm('Create and send this invoice via Stripe?')">
                    Create & Send Invoice
                </button>
            </div>
        </form>
    </div>

    <script>
        function invoiceForm() {
            return {
                items: [{description: '', amount: ''}]
            }
        }
    </script>
</x-admin-layout>
