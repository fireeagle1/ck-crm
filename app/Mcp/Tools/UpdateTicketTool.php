<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpBotUserResolver;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Validation\ValidationException;

/**
 * MCP write tool backing `update_ticket` (scope `tickets:write`, handler key
 * `tickets.update`).
 *
 * Updates an existing ticket's status, priority, type, assignment, related
 * asset/service, and due date through the shared {@see TicketService::update()}
 * path, so MCP-originated updates produce exactly the same side effects as the
 * admin API: tracked-field {@see \App\Models\TicketActivity} rows attributed to
 * the actor (Req 10.2), a customer status-change email on status transitions
 * (Req 10.3), and a {@see \App\Notifications\CriticalTicketNotification} to
 * admins when the priority becomes Critical (Req 10.4). The write is attributed
 * to the dedicated least-privilege bot actor ({@see McpBotUserResolver}), which
 * also tags the activity `source` as `mcp`.
 *
 * The tool is the transport-facing seam between a validated `tools/call`
 * argument map and the service. It is intentionally NOT registered with the
 * {@see \App\Mcp\Registry\ToolDispatcher} here — the handler key `tickets.update`
 * is wired by task 12.1.
 *
 * No-mutation-on-failure (Req 10.5) is enforced two ways:
 *
 *  - If no ticket matches the supplied `ticket_id`, the tool returns a
 *    structured not-found error BEFORE touching the service, so nothing is
 *    written.
 *  - If the update fields fail the admin validation rules,
 *    {@see TicketService::update()} throws {@see ValidationException} before it
 *    mutates the ticket or writes any activity; the tool catches it and returns
 *    a structured field-level error. The service validates first, so a reject
 *    leaves the ticket, its activities, and all notifications untouched.
 *
 * The error envelope mirrors the transport's own schema-validation error
 * ({@see \App\Http\Controllers\Mcp\McpController}) — `{ error: { code, message,
 * fields? } }` — so the connector sees a consistent shape whether the failure
 * is caught at the schema gate or here in the tool.
 */
final class UpdateTicketTool
{
    /**
     * Error code for a reference to a ticket that does not exist, aligned with
     * the JSON-RPC "invalid params" code used by the transport for bad input.
     */
    private const ERR_NOT_FOUND = -32602;

    /**
     * Error code for update fields that fail the admin validation rules,
     * aligned with the JSON-RPC "invalid params" code.
     */
    private const ERR_VALIDATION = -32602;

    public function __construct(
        private readonly TicketService $tickets,
        private readonly McpBotUserResolver $botResolver,
    ) {
    }

    /**
     * Handle an `update_ticket` tool call.
     *
     * Expects the validated `params.arguments` map: a required integer
     * `ticket_id` identifying the ticket, plus any subset of the updatable
     * fields (`status`, `priority`, `ticket_type`, `asset_id`, `service_id`,
     * `user_id`, `due_at`). The arguments have already passed the transport's
     * JSON-schema gate (required `ticket_id`, enum membership, scalar types);
     * this method enforces the existence and the full admin update rules.
     *
     * @param  array<string, mixed>  $arguments  The validated tool arguments.
     * @return array<string, mixed> A compact summary on success, or a structured
     *                              error ({ error: { code, message, fields? } })
     *                              on a missing ticket or validation failure —
     *                              in which case NO mutation is performed.
     */
    public function handle(array $arguments): array
    {
        $ticketId = (int) ($arguments['ticket_id'] ?? 0);

        $ticket = Ticket::query()->whereKey($ticketId)->first();

        // No-mutation-on-failure (Req 10.5): an unknown ticket is rejected
        // before the service is touched, so nothing is written.
        if ($ticket === null) {
            return $this->notFoundError($ticketId);
        }

        // The update data is every argument EXCEPT the ticket selector; the
        // service validates and applies only the recognised update fields.
        $data = $arguments;
        unset($data['ticket_id']);

        $actor = $this->botResolver->resolve();

        try {
            $ticket = $this->tickets->update($ticket, $data, $actor);
        } catch (ValidationException $e) {
            // The service validates before mutating, so a reject leaves the
            // ticket, its activities, and notifications untouched (Req 10.5).
            return $this->validationError($e);
        }

        return $this->summary($ticket);
    }

    /**
     * The compact success summary returned to the connector.
     *
     * @return array<string, mixed>
     */
    private function summary(Ticket $ticket): array
    {
        return [
            'ticket_id' => $ticket->ticket_id,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'ticket_type' => $ticket->ticket_type,
            'company_id' => $ticket->company_id,
        ];
    }

    /**
     * A structured not-found error for a missing ticket. No mutation occurs.
     *
     * @return array<string, mixed>
     */
    private function notFoundError(int $ticketId): array
    {
        return [
            'error' => [
                'code' => self::ERR_NOT_FOUND,
                'message' => sprintf('Ticket %d does not exist.', $ticketId),
                'fields' => [
                    'ticket_id' => ['The selected ticket_id does not exist.'],
                ],
            ],
        ];
    }

    /**
     * A structured field-level error from a failed admin validation. Naming the
     * offending fields matches the transport's schema-validation error shape.
     *
     * @return array<string, mixed>
     */
    private function validationError(ValidationException $e): array
    {
        return [
            'error' => [
                'code' => self::ERR_VALIDATION,
                'message' => 'Invalid arguments for "update_ticket".',
                'fields' => $e->errors(),
            ],
        ];
    }
}
