<x-portal-layout>
    <x-slot:title>My Projects</x-slot:title>

    <h1 class="text-3xl font-bold tracking-tight mb-2">My Projects</h1>
    <p class="text-gray-500 mb-6">Track the progress of your active projects.</p>

    @if ($projects->isEmpty())
        <div class="bg-white rounded-lg border p-8 text-center">
            <p class="text-gray-500">You have no active projects.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($projects as $project)
                <a href="{{ route('portal.projects.show', $project) }}" class="block bg-white rounded-lg border p-5 hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-semibold text-gray-900">{{ $project->title }}</h2>
                        @php
                            $statusClasses = match($project->status) {
                                'Not Started' => 'bg-gray-100 text-gray-700',
                                'In Progress' => 'bg-blue-100 text-blue-700',
                                'On Hold' => 'bg-yellow-100 text-yellow-700',
                                'Awaiting Approval' => 'bg-purple-100 text-purple-700',
                                default => 'bg-gray-100 text-gray-700',
                            };
                        @endphp
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClasses }}">
                            {{ $project->status }}
                        </span>
                    </div>

                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-green-500 h-2.5 rounded-full" style="width: {{ $project->progress_percentage }}%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1.5">{{ $project->progress_percentage }}% complete</p>
                </a>
            @endforeach
        </div>
    @endif
</x-portal-layout>
