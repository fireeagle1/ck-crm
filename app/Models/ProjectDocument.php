<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProjectDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'label',
        'document_type',
        'file_path',
        'original_filename',
        'file_size',
        'uploaded_by',
    ];

    const TYPES = ['Agreement', 'Contract', 'Quote', 'Design Asset', 'Other'];
    const ALLOWED_EXTENSIONS = ['pdf', 'docx', 'xlsx', 'png', 'jpg', 'zip'];
    const MAX_SIZE_MB = 20;

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approvalRequest(): HasOne
    {
        return $this->hasOne(ProjectApprovalRequest::class, 'project_document_id');
    }
}
