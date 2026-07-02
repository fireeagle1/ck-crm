<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectDocumentRequest;
use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Support\Facades\Storage;

class ProjectDocumentController extends Controller
{
    public function store(StoreProjectDocumentRequest $request, Project $project)
    {
        $file = $request->file('file');

        try {
            $path = Storage::putFile('project-documents/' . $project->id, $file);
        } catch (\Exception $e) {
            return back()->with('error', 'File upload failed. Please try again.');
        }

        ProjectDocument::create([
            'project_id' => $project->id,
            'label' => $request->input('label'),
            'document_type' => $request->input('document_type'),
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()->route('admin.projects.show', $project)
            ->with('success', 'Document uploaded successfully.');
    }

    public function destroy(Project $project, ProjectDocument $document)
    {
        Storage::delete($document->file_path);

        $document->delete();

        return back()->with('success', 'Document deleted successfully.');
    }
}
