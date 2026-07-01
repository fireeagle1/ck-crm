<x-portal-layout>
    <x-slot:title>Invoices</x-slot:title>

    <h1 class="text-2xl font-semibold mb-4">Invoices</h1>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Date</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Amount</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Due</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($invoices as $invoice)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">{{ $invoice->invoice_date?->format('M j, Y') ?? '—' }}</td>
                        <td class="px-4 py-3 font-medium">£{{ number_format($invoice->invoice_amount, 2) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $invoice->invoice_status === 'Paid' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $invoice->invoice_status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $invoice->due_date?->format('M j, Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">No invoices.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>
</x-portal-layout>
