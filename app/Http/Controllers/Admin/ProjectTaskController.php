<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectTaskRequest;
use App\Http\Requests\UpdateProjectTaskRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Http\Request;

class ProjectTaskController extends Controller
{
    public function index(Project $project)
    {
        $tasks = $project->tasks()->orderBy('display_order')->get();

        return view('admin.projects.tasks.index', compact('project', 'tasks'));
    }

    public function create(Project $project)
    {
        return view('admin.projects.tasks.create', compact('project'));
    }

    public function store(StoreProjectTaskRequest $request, Project $project)
    {
        $validated = $request->validated();

        if (!isset($validated['display_order'])) {
            $validated['display_order'] = $project->tasks()->max('display_order') + 1;
        }

        $project->tasks()->create($validated);

        return redirect()->route('admin.projects.show', $project)
            ->with('success', 'Task created successfully.');
    }

    public function edit(Project $project, ProjectTask $task)
    {
        return view('admin.projects.tasks.edit', compact('project', 'task'));
    }

    public function update(UpdateProjectTaskRequest $request, Project $project, ProjectTask $task)
    {
        $task->update($request->validated());

        return redirect()->route('admin.projects.show', $project)
            ->with('success', 'Task updated successfully.');
    }

    public function destroy(Project $project, ProjectTask $task)
    {
        $task->delete();

        return redirect()->route('admin.projects.show', $project)
            ->with('success', 'Task deleted successfully.');
    }

    public function reorder(Request $request, Project $project)
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:project_tasks,id'],
        ]);

        foreach ($request->input('order') as $index => $taskId) {
            ProjectTask::where('id', $taskId)
                ->where('project_id', $project->id)
                ->update(['display_order' => $index]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('admin.projects.show', $project)
            ->with('success', 'Tasks reordered successfully.');
    }
}
