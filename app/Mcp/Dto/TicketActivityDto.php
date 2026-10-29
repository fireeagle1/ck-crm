<?php

namespace App\Mcp\Dto;

use App\Models\TicketActivity;

/**
 * Data-minimisation transformer for a ticket activity (history) entry.
 *
 * Exposes the actor's full name via user_name only — the user row is never
 * returned. The human-readable description is sourced from the model accessor.
 */
final class TicketActivityDto
{
    public static function fromModel(TicketActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'type' => $activity->type,
            'old_value' => $activity->old_value,
            'new_value' => $activity->new_value,
            'note' => $activity->note,
            'description' => $activity->description,
            'user_name' => $activity->user?->full_name,
            'created_at' => self::datetime($activity->created_at),
        ];
    }

    private static function datetime($value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? $value->format(\DateTimeInterface::ATOM)
            : (string) $value;
    }
}
