<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    const STATUSES = ['Not Started', 'In Progress', 'On Hold', 'Awaiting Approval', 'Completed'];

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'status',
        'previous_status',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'company_id', 'company_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProjectComment::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ProjectDecision::class);
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ProjectApprovalRequest::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ProjectStatusLog::class);
    }

    public function getProgressPercentageAttribute(): int
    {
        $total = $this->tasks()->count();
        if ($total === 0) {
            return 0;
        }
        $done = $this->tasks()->where('status', 'Done')->count();

        return (int) floor(($done / $total) * 100);
    }
}
