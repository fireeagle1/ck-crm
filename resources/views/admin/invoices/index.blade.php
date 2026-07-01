<x-admin-layout>
    <x-slot:title>Invoices</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Invoices</h1>
        <div class="flex gap-2">
            @foreach (['all' => 'All', 'unpaid' => 'Unpaid', 'paid' => 'Paid'] as $key => $label)
                <a href="{{ route('admin.invoices.index', ['status' => $key]) }}"
                   class="px-3 py-1.5 rounded-md text-sm font-medium {{ $status === $key ? 'bg-blue-600 text-white' : 'border hover:bg-gray-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <p class="text-sm text-gray-500">Total Unpaid</p>
            <p class="text-2xl font-semibold mt-1 {{ $totalUnpaid > 0 ? 'text-red-600' : '' }}">£{{ number_format($totalUnpaid, 2) }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border">
            <p class="text-sm text-gray-500">Collected This Month</p>
            <p class="text-2xl font-semibold mt-1">£{{ number_format($totalPaidThisMonth, 2) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Invoice</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Customer</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Amount</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Date</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Due</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($invoices as $invoice)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium font-mono text-xs">
                            {{ $invoice->stripe_invoice_id ? Str::limit($invoice->stripe_invoice_id, 20) : '#' . $invoice->invoice_id }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($invoice->customer)
                                <a href="{{ route('admin.customers.show', $invoice->customer) }}" class="text-blue-600 hover:underline">
                                    {{ $invoice->customer->company_name }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium">£{{ number_format($invoice->invoice_amount, 2) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $invoice->invoice_status === 'Paid' ? 'bg-green-100 text-green-700' : ($invoice->invoice_status === 'Unpaid' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700') }}">
                                {{ $invoice->invoice_status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $invoice->invoice_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500 {{ $invoice->invoice_status === 'Unpaid' && $invoice->due_date?->isPast() ? 'text-red-600 font-medium' : '' }}">
                            {{ $invoice->due_date?->format('Y-m-d') ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No invoices found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>
</x-admin-layout>
