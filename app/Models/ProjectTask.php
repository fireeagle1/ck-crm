<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'status',
        'display_order',
    ];

    const STATUSES = ['To Do', 'In Progress', 'Done'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
