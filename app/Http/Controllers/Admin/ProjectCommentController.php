<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectCommentRequest;
use App\Models\Project;
use App\Models\ProjectComment;
use App\Services\ProjectNotificationService;
use Illuminate\Http\RedirectResponse;

class ProjectCommentController extends Controller
{
    public function __construct(
        protected ProjectNotificationService $notificationService
    ) {}

    public function store(StoreProjectCommentRequest $request, Project $project): RedirectResponse
    {
        $comment = ProjectComment::create([
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'body' => $request->validated('body'),
            'is_internal' => $request->validated('is_internal', false),
        ]);

        // If public comment, notify customer users
        if (!$comment->is_internal) {
            $sent = $this->notificationService->notifyPublicComment($project, $comment);

            if (!$sent) {
                return redirect()->back()
                    ->with('success', 'Comment added.')
                    ->with('warning', 'Customer notification could not be sent. Check logs for details.');
            }
        }

        return redirect()->back()
            ->with('success', 'Comment added.');
    }
}
