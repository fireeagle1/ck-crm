<?php

namespace App\Mcp\Dto;

use App\Models\Project;

/**
 * Data-minimisation transformer for the Project domain.
 *
 * Exposes the computed progress percentage via the model accessor rather than
 * any raw relation data. Model attributes are never spread.
 */
final class ProjectDto
{
    public static function fromModel(Project $project): array
    {
        return [
            'id' => $project->id,
            'company_id' => $project->company_id,
            'title' => $project->title,
            'status' => $project->status,
            'progress_percentage' => $project->progress_percentage,
        ];
    }
}
