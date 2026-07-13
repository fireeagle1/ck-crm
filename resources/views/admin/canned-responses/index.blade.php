<x-admin-layout>
    <x-slot:title>Canned Responses</x-slot:title>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Canned Responses</h1>
        <a href="{{ route('admin.canned-responses.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700 transition">
            + New Response
        </a>
    </div>

    <p class="text-sm text-gray-500 mb-4">Quick reply templates that can be inserted when replying to tickets.</p>

    <div class="bg-white rounded-lg border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Title</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Category</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Preview</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Order</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($responses as $response)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $response->title }}</td>
                        <td class="px-4 py-3 text-gray-500">
                            @if ($response->category)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-blue-50 text-blue-700">
                                    {{ $response->category }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ Str::limit($response->body, 60) }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $response->sort_order }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.canned-responses.edit', $response) }}" class="text-blue-600 hover:underline text-sm font-medium">Edit</a>
                                <form method="POST" action="{{ route('admin.canned-responses.destroy', $response) }}" class="inline"
                                      onsubmit="return confirm('Delete this canned response?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline text-sm font-medium">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">No canned responses yet. Create one to speed up ticket replies.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
