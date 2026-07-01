<x-admin-layout>
    <x-slot:title>Customers</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Customers</h1>
        <a href="{{ route('admin.customers.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
            Add Customer
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Company</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Services</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Users</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Tickets</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($customers as $customer)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('admin.customers.show', $customer) }}" class="text-blue-600 hover:underline">
                                {{ $customer->company_name }}
                            </a>
                        </td>
                        <td class="px-4 py-3">{{ $customer->services_count }}</td>
                        <td class="px-4 py-3">{{ $customer->users_count }}</td>
                        <td class="px-4 py-3">{{ $customer->tickets_count }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.customers.edit', $customer) }}" class="text-blue-600 hover:underline text-sm">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">No customers yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $customers->links() }}
    </div>
</x-admin-layout>
