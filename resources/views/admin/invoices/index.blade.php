<x-admin-layout>
    <x-slot:title>Invoices</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Invoices</h1>
        <a href="{{ route('admin.invoices.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700">+ Create Invoice</a>
    </div>

    {{-- Filters + Search --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex gap-1">
            @foreach (['all' => 'All', 'unpaid' => 'Unpaid', 'paid' => 'Paid'] as $key => $label)
                <a href="{{ route('admin.invoices.index', ['status' => $key, 'q' => $search]) }}"
                   class="px-3 py-1.5 rounded-md text-sm font-semibold {{ $status === $key ? 'bg-blue-600 text-white' : 'border hover:bg-gray-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form action="{{ route('admin.invoices.index') }}" method="GET" class="flex gap-2 ml-auto">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search customer or invoice..."
                   class="w-64 rounded-md border-gray-300 text-sm px-3 py-1.5 focus:ring-blue-500 focus:border-blue-500">
            <button type="submit" class="px-3 py-1.5 bg-gray-100 border rounded-md text-sm hover:bg-gray-200">Search</button>
        </form>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg p-4 border">
            <p class="text-sm font-medium text-gray-500">Total Unpaid</p>
            <p class="text-2xl font-bold mt-1 {{ $totalUnpaid > 0 ? 'text-red-600' : '' }}">£{{ number_format($totalUnpaid, 2) }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 border">
            <p class="text-sm font-medium text-gray-500">Overdue</p>
            <p class="text-2xl font-bold mt-1 {{ $overdueCount > 0 ? 'text-red-600' : '' }}">{{ $overdueCount }}</p>
        </div>
        <div class="bg-white rounded-lg p-4 border">
            <p class="text-sm font-medium text-gray-500">Collected This Month</p>
            <p class="text-2xl font-bold mt-1">£{{ number_format($totalPaidThisMonth, 2) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-lg border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Invoice</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Customer</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Amount</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Date</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Due</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($invoices as $invoice)
                    @php $overdue = $invoice->invoice_status === 'Unpaid' && $invoice->due_date?->isPast(); @endphp
                    <tr class="hover:bg-gray-50 {{ $overdue ? 'bg-red-50' : '' }}">
                        <td class="px-4 py-3 font-mono text-xs">
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
                        <td class="px-4 py-3 font-semibold">£{{ number_format($invoice->invoice_amount, 2) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                {{ $invoice->invoice_status === 'Paid' ? 'bg-green-100 text-green-700' : ($overdue ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ $overdue ? 'Overdue' : $invoice->invoice_status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $invoice->invoice_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-4 py-3 {{ $overdue ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                            {{ $invoice->due_date?->format('Y-m-d') ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                @if ($invoice->stripe_hosted_url)
                                    <a href="{{ $invoice->stripe_hosted_url }}" target="_blank" class="text-blue-600 hover:underline text-xs font-medium">Open</a>
                                @endif
                                @if ($invoice->invoice_status === 'Unpaid')
                                    <form method="POST" action="{{ route('admin.invoices.remind', $invoice) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-amber-600 hover:underline text-xs font-medium">Remind</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">No invoices found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $invoices->appends(request()->query())->links() }}</div>
</x-admin-layout>
