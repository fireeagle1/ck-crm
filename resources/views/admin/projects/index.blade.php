<x-admin-layout>
    <x-slot:title>Projects</x-slot:title>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-md text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-md text-sm">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Projects</h1>
        <a href="{{ route('admin.projects.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-semibold hover:bg-blue-700">+ New Project</a>
    </div>

    <div class="bg-white rounded-lg border overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Title</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Customer</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Progress</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($projects as $project)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $project->title }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $project->customer->company_name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $statusColors = [
                                    'Not Started' => 'bg-gray-100 text-gray-700',
                                    'In Progress' => 'bg-blue-100 text-blue-700',
                                    'On Hold' => 'bg-yellow-100 text-yellow-700',
                                    'Awaiting Approval' => 'bg-purple-100 text-purple-700',
                                    'Completed' => 'bg-green-100 text-green-700',
                                ];
                            @endphp
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $statusColors[$project->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $project->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-24 bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $project->progress_percentage }}%"></div>
                                </div>
                                <span class="text-xs text-gray-500">{{ $project->progress_percentage }}%</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.projects.show', $project) }}" class="text-blue-600 hover:underline text-xs">View</a>
                                <a href="{{ route('admin.projects.edit', $project) }}" class="text-gray-600 hover:underline text-xs">Edit</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">No projects found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
