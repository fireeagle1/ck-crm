<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'project_document_id',
        'type',
        'status',
        'responded_by',
        'responded_at',
        'rejection_reason',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    const TYPES = ['Document Approval', 'Project Completion'];
    const STATUSES = ['Pending', 'Approved', 'Rejected'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ProjectDocument::class, 'project_document_id');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
