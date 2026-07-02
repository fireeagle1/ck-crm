<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectApprovalRequest;
use App\Models\ProjectDocument;
use App\Models\ProjectStatusLog;
use App\Services\ProjectNotificationService;
use Illuminate\Http\RedirectResponse;

class ProjectApprovalController extends Controller
{
    public function __construct(
        protected ProjectNotificationService $notificationService
    ) {}

    public function requestDocumentApproval(Project $project, ProjectDocument $document): RedirectResponse
    {
        // Check if document already has a Pending approval
        if ($document->approvalRequest()->where('status', 'Pending')->exists()) {
            return redirect()->back()
                ->with('error', 'An approval request is already pending for this document.');
        }

        $approval = ProjectApprovalRequest::create([
            'project_id' => $project->id,
            'project_document_id' => $document->id,
            'type' => 'Document Approval',
            'status' => 'Pending',
        ]);

        $sent = $this->notificationService->notifyApprovalRequest($project, $approval);

        if (!$sent) {
            return redirect()->route('admin.projects.show', $project)
                ->with('success', 'Document approval requested.')
                ->with('warning', 'Customer notification could not be sent. Check logs for details.');
        }

        return redirect()->route('admin.projects.show', $project)
            ->with('success', 'Document approval requested.');
    }

    public function requestCompletionApproval(Project $project): RedirectResponse
    {
        // Check if project already has a pending completion approval
        if ($project->approvalRequests()->where('type', 'Project Completion')->where('status', 'Pending')->exists()) {
            return redirect()->back()
                ->with('error', 'A completion approval is already pending.');
        }

        // Store previous status and set project to Awaiting Approval
        $project->update([
            'previous_status' => $project->status,
            'status' => 'Awaiting Approval',
        ]);

        // Log status change
        ProjectStatusLog::create([
            'project_id' => $project->id,
            'status' => 'Awaiting Approval',
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]);

        // Create approval request
        $approval = ProjectApprovalRequest::create([
            'project_id' => $project->id,
            'type' => 'Project Completion',
            'status' => 'Pending',
        ]);

        $sent = $this->notificationService->notifyApprovalRequest($project, $approval);

        if (!$sent) {
            return redirect()->route('admin.projects.show', $project)
                ->with('success', 'Completion approval requested.')
                ->with('warning', 'Customer notification could not be sent. Check logs for details.');
        }

        return redirect()->route('admin.projects.show', $project)
            ->with('success', 'Completion approval requested.');
    }
}
