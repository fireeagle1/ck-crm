<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectApprovalRequest;
use App\Models\ProjectComment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProjectNotificationService
{
    /**
     * Send email notification to all customer users when a public comment is added.
     *
     * @return bool True on success, false on failure.
     */
    public function notifyPublicComment(Project $project, ProjectComment $comment): bool
    {
        $recipients = $this->getCustomerUsers($project);

        if ($recipients->isEmpty()) {
            return true;
        }

        $summary = $this->getNotificationSummary($comment->body);
        $siteName = Setting::get('site_name', 'CK Enterprises UK');

        try {
            foreach ($recipients as $recipient) {
                Mail::send('emails.project-comment', [
                    'project' => $project,
                    'comment' => $comment,
                    'summary' => $summary,
                    'recipientName' => $recipient->first_name ?? 'there',
                ], function ($message) use ($recipient, $project, $siteName) {
                    $message->to($recipient->email)
                            ->subject("Project Update: {$project->title} — {$siteName}");
                });
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send public comment notification', [
                'project_id' => $project->id,
                'comment_id' => $comment->id,
                'recipients' => $recipients->pluck('email')->toArray(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send email notification to all customer users when an approval is requested.
     *
     * @return bool True on success, false on failure.
     */
    public function notifyApprovalRequest(Project $project, ProjectApprovalRequest $approval): bool
    {
        $recipients = $this->getCustomerUsers($project);

        if ($recipients->isEmpty()) {
            return true;
        }

        $siteName = Setting::get('site_name', 'CK Enterprises UK');
        $portalLink = route('portal.projects.show', $project);

        try {
            foreach ($recipients as $recipient) {
                Mail::send('emails.project-approval-request', [
                    'project' => $project,
                    'approval' => $approval,
                    'portalLink' => $portalLink,
                    'recipientName' => $recipient->first_name ?? 'there',
                ], function ($message) use ($recipient, $project, $siteName) {
                    $message->to($recipient->email)
                            ->subject("Approval Required: {$project->title} — {$siteName}");
                });
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send approval request notification', [
                'project_id' => $project->id,
                'approval_id' => $approval->id,
                'recipients' => $recipients->pluck('email')->toArray(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send email notification to admin users when a completion approval is rejected.
     *
     * @return bool True on success, false on failure.
     */
    public function notifyCompletionRejection(Project $project, ProjectApprovalRequest $approval): bool
    {
        $admins = User::where('is_admin', true)->get();

        if ($admins->isEmpty()) {
            return true;
        }

        $siteName = Setting::get('site_name', 'CK Enterprises UK');

        try {
            foreach ($admins as $admin) {
                Mail::send('emails.project-completion-rejected', [
                    'project' => $project,
                    'approval' => $approval,
                    'rejectionReason' => $approval->rejection_reason,
                    'recipientName' => $admin->first_name ?? 'there',
                ], function ($message) use ($admin, $project, $siteName) {
                    $message->to($admin->email)
                            ->subject("Project Completion Rejected: {$project->title} — {$siteName}");
                });
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send completion rejection notification', [
                'project_id' => $project->id,
                'approval_id' => $approval->id,
                'recipients' => $admins->pluck('email')->toArray(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Truncate text to a maximum length for use in notification summaries.
     * If text exceeds maxLength, it is truncated and '...' is appended,
     * ensuring total output length does not exceed maxLength.
     */
    public function getNotificationSummary(string $text, int $maxLength = 200): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 3) . '...';
    }

    /**
     * Resolve all customer users for the project's associated company.
     */
    protected function getCustomerUsers(Project $project): \Illuminate\Database\Eloquent\Collection
    {
        return $project->customer->users()->where('is_admin', false)->get();
    }
}
