<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectDecisionRequest;
use App\Models\Project;
use App\Models\ProjectDecision;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProjectDecisionController extends Controller
{
    public function index(Project $project): View
    {
        $decisions = $project->decisions()
            ->orderBy('date_recorded', 'desc')
            ->get();

        return view('admin.projects.decisions.index', compact('project', 'decisions'));
    }

    public function create(Project $project): View
    {
        return view('admin.projects.decisions.create', compact('project'));
    }

    public function store(StoreProjectDecisionRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['date_recorded'])) {
            $data['date_recorded'] = now()->toDateString();
        }

        $project->decisions()->create($data);

        return redirect()->route('admin.projects.show', $project)
            ->with('success', 'Decision recorded.');
    }

    public function edit(Project $project, ProjectDecision $decision): View
    {
        return view('admin.projects.decisions.edit', compact('project', 'decision'));
    }

    public function update(StoreProjectDecisionRequest $request, Project $project, ProjectDecision $decision): RedirectResponse
    {
        $decision->update($request->validated());

        return redirect()->route('admin.projects.show', $project)
            ->with('success', 'Decision updated.');
    }

    public function destroy(Project $project, ProjectDecision $decision): RedirectResponse
    {
        $decision->delete();

        return redirect()->back()
            ->with('success', 'Decision deleted.');
    }
}
