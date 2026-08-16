<x-admin-layout>
    <x-slot:title>Discount Codes</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-semibold">Discount Codes</h1>
        <a href="{{ route('admin.shop.discount-codes.create') }}"
           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition">
            + New Code
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Code</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Type</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Value</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Usage</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Valid Period</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($discountCodes as $code)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <code class="bg-gray-100 px-2 py-0.5 rounded text-sm font-mono font-semibold">{{ $code->code }}</code>
                        </td>
                        <td class="px-4 py-3 capitalize">{{ $code->type }}</td>
                        <td class="px-4 py-3">
                            @if ($code->type === 'percentage')
                                {{ number_format($code->value, 0) }}%
                                @if ($code->max_discount_amount)
                                    <span class="text-xs text-gray-500">(max &pound;{{ number_format($code->max_discount_amount, 2) }})</span>
                                @endif
                            @else
                                &pound;{{ number_format($code->value, 2) }}
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            {{ $code->times_used }}{{ $code->usage_limit ? ' / ' . $code->usage_limit : '' }}
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            @if ($code->valid_from || $code->valid_until)
                                {{ $code->valid_from?->format('d M Y') ?? '—' }}
                                &rarr;
                                {{ $code->valid_until?->format('d M Y') ?? '—' }}
                            @else
                                No limit
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($code->isValid())
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">Active</span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <a href="{{ route('admin.shop.discount-codes.edit', $code) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Edit</a>
                            <form method="POST" action="{{ route('admin.shop.discount-codes.destroy', $code) }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" onclick="return confirm('Delete this discount code?')" class="text-red-600 hover:text-red-800 text-sm font-medium">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">No discount codes yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $discountCodes->links() }}
    </div>
</x-admin-layout>
