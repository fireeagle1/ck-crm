<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;

        $projects = Project::where('company_id', $companyId)
            ->where('status', '!=', 'Completed')
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('portal.projects.index', compact('projects'));
    }

    public function show(Request $request, Project $project): View
    {
        if ($project->company_id !== $request->user()->company_id) {
            abort(404);
        }

        $project->load([
            'tasks' => fn ($q) => $q->orderBy('display_order'),
            'comments' => fn ($q) => $q->where('is_internal', false)->orderBy('created_at', 'asc'),
            'documents',
            'decisions' => fn ($q) => $q->orderBy('date_recorded', 'desc'),
            'approvalRequests' => fn ($q) => $q->where('status', 'Pending'),
        ]);

        return view('portal.projects.show', compact('project'));
    }

    public function downloadDocument(Request $request, Project $project, ProjectDocument $document): StreamedResponse
    {
        if ($project->company_id !== $request->user()->company_id) {
            abort(404);
        }

        if ($document->project_id !== $project->id) {
            abort(404);
        }

        return Storage::download($document->file_path, $document->original_filename);
    }
}
