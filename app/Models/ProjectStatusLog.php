<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStatusLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'status',
        'changed_by',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
