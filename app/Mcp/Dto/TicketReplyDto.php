<?php

namespace App\Mcp\Dto;

use App\Models\TicketReply;

/**
 * Data-minimisation transformer for a ticket reply.
 *
 * Exposes the author's full name via user_name only — the user row is never
 * returned, so no user security field (password, tokens, etc.) can leak.
 * Both internal and non-internal replies are representable; the caller
 * decides which to include.
 */
final class TicketReplyDto
{
    public static function fromModel(TicketReply $reply): array
    {
        return [
            'id' => $reply->id,
            'body' => $reply->body,
            'is_internal' => (bool) $reply->is_internal,
            'user_name' => $reply->user?->full_name,
            'created_at' => self::datetime($reply->created_at),
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
