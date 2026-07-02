<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\RejectApprovalRequest;
use App\Models\Project;
use App\Models\ProjectApprovalRequest;
use App\Models\ProjectStatusLog;
use App\Services\ProjectNotificationService;
use Illuminate\Http\RedirectResponse;

class ProjectApprovalController extends Controller
{
    public function __construct(
        protected ProjectNotificationService $notificationService
    ) {}

    public function approve(Project $project, ProjectApprovalRequest $approval): RedirectResponse
    {
        // Verify project belongs to user's company
        if ($project->company_id !== auth()->user()->company_id) {
            abort(404);
        }

        // Block responses on non-Pending approval requests
        if ($approval->status !== 'Pending') {
            return redirect()->back()
                ->with('error', 'This approval request has already been resolved.');
        }

        // Update approval status
        $approval->update([
            'status' => 'Approved',
            'responded_by' => auth()->id(),
            'responded_at' => now(),
        ]);

        // If completion type, set project status to Completed
        if ($approval->type === 'Project Completion') {
            $project->update(['status' => 'Completed']);

            ProjectStatusLog::create([
                'project_id' => $project->id,
                'status' => 'Completed',
                'changed_by' => auth()->id(),
                'created_at' => now(),
            ]);
        }

        return redirect()->back()
            ->with('success', 'Approval submitted successfully.');
    }

    public function reject(RejectApprovalRequest $request, Project $project, ProjectApprovalRequest $approval): RedirectResponse
    {
        // Verify project belongs to user's company
        if ($project->company_id !== auth()->user()->company_id) {
            abort(404);
        }

        // Block responses on non-Pending approval requests
        if ($approval->status !== 'Pending') {
            return redirect()->back()
                ->with('error', 'This approval request has already been resolved.');
        }

        // Update approval status
        $approval->update([
            'status' => 'Rejected',
            'responded_by' => auth()->id(),
            'responded_at' => now(),
            'rejection_reason' => $request->validated('reason'),
        ]);

        // If completion type, revert project status and notify admin
        if ($approval->type === 'Project Completion') {
            $project->update(['status' => $project->previous_status]);

            ProjectStatusLog::create([
                'project_id' => $project->id,
                'status' => $project->previous_status,
                'changed_by' => auth()->id(),
                'created_at' => now(),
            ]);

            $this->notificationService->notifyCompletionRejection($project, $approval);
        }

        return redirect()->back()
            ->with('success', 'Approval submitted successfully.');
    }
}
