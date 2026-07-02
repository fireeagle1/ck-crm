<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'category',
        'date_recorded',
    ];

    protected $casts = [
        'date_recorded' => 'date',
    ];

    const CATEGORIES = ['Design Requirement', 'Client Decision', 'Technical Decision'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
