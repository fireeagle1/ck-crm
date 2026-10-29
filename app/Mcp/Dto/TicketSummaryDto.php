<?php

namespace App\Mcp\Dto;

use App\Models\Ticket;

/**
 * Data-minimisation transformer for a ticket summary (search/list rows).
 *
 * Returns a compact allow-listed view. The customer's name is read from the
 * related customer record (customer_name) rather than traversing users.
 * Model attributes are never spread.
 */
final class TicketSummaryDto
{
    public static function fromModel(Ticket $ticket): array
    {
        return [
            'ticket_id' => $ticket->ticket_id,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'ticket_type' => $ticket->ticket_type,
            'customer_name' => $ticket->customer?->customer_name,
            'company_id' => $ticket->company_id,
        ];
    }
}
