<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectStatusLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        $projects = Project::with('customer')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.projects.index', compact('projects'));
    }

    public function create(): View
    {
        $customers = Customer::orderBy('company_name')->get();

        return view('admin.projects.create', compact('customers'));
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = Project::create($request->validated());

        ProjectStatusLog::create([
            'project_id' => $project->id,
            'status' => 'Not Started',
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]);

        return redirect()->route('admin.projects.show', $project)
            ->with('success', 'Project created.');
    }

    public function show(Project $project): View
    {
        $project->load([
            'customer',
            'tasks' => fn ($q) => $q->orderBy('display_order'),
            'comments' => fn ($q) => $q->with('user')->orderBy('created_at'),
            'decisions' => fn ($q) => $q->orderBy('date_recorded', 'desc'),
            'documents.uploader',
            'documents.approvalRequest',
            'approvalRequests.document',
            'approvalRequests.respondedBy',
            'statusLogs' => fn ($q) => $q->with('changedBy')->orderBy('created_at', 'desc'),
        ]);

        return view('admin.projects.show', compact('project'));
    }

    public function edit(Project $project): View
    {
        return view('admin.projects.edit', compact('project'));
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $validated = $request->validated();
        $newStatus = $validated['status'] ?? $project->status;

        // Block status changes on Completed projects (must use reopen action)
        if ($project->status === 'Completed' && $newStatus !== 'Completed') {
            return redirect()->back()
                ->with('error', 'This project is completed. Please reopen it first.');
        }

        $oldStatus = $project->status;

        $project->update($validated);

        // Log status change if status actually changed
        if ($oldStatus !== $newStatus) {
            ProjectStatusLog::create([
                'project_id' => $project->id,
                'status' => $newStatus,
                'changed_by' => auth()->id(),
                'created_at' => now(),
            ]);
        }

        return redirect()->route('admin.projects.show', $project)
            ->with('success', 'Project updated.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()->route('admin.projects.index')
            ->with('success', 'Project deleted.');
    }

    public function reopen(Project $project): RedirectResponse
    {
        if ($project->status !== 'Completed') {
            return redirect()->back()
                ->with('error', 'Only completed projects can be reopened.');
        }

        $project->update(['status' => 'In Progress']);

        ProjectStatusLog::create([
            'project_id' => $project->id,
            'status' => 'In Progress',
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]);

        return redirect()->back()
            ->with('success', 'Project reopened.');
    }
}
