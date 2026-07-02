<x-admin-layout>
    <x-slot:title>Customers</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Customers</h1>
        <a href="{{ route('admin.customers.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700">+ Add Customer</a>
    </div>

    {{-- Search --}}
    <div class="mb-4">
        <form method="GET" action="{{ route('admin.customers.index') }}" class="flex items-center gap-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by company or contact name..."
                   class="w-full max-w-md rounded-md border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
            <button type="submit" class="px-4 py-2 bg-gray-100 border rounded-md text-sm font-medium hover:bg-gray-200">Search</button>
            @if(request('q'))
                <a href="{{ route('admin.customers.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Clear</a>
            @endif
        </form>
    </div>

    <div class="bg-white rounded-lg border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Company</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Contact</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Services</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Users</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Tickets</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($customers as $customer)
                    <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('admin.customers.show', $customer) }}'">
                        <td class="px-4 py-3 font-medium text-blue-600">{{ $customer->company_name ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $customer->customer_name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $customer->services_count }}</td>
                        <td class="px-4 py-3">{{ $customer->users_count }}</td>
                        <td class="px-4 py-3">{{ $customer->tickets_count }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">No customers found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $customers->appends(request()->query())->links() }}</div>
</x-admin-layout>
