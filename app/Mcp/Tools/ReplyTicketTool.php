<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpBotUserResolver;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Validation\ValidationException;

/**
 * MCP write tool backing `reply_ticket` (scope `tickets:write`).
 *
 * Adds a reply — or an internal note — to an existing ticket, attributing the
 * reply and any resulting activity to the dedicated, least-privilege MCP bot
 * user ({@see McpBotUserResolver}) rather than a real admin (Req 11.5). The
 * write itself is delegated verbatim to {@see TicketService::reply()}, the same
 * code path the admin API uses, so the observable side effects are identical:
 * a non-internal reply emails the customer and may reopen a Closed ticket with
 * a status-change activity, the first non-internal reply stamps
 * `first_replied_at`, and internal notes do neither (Req 11.2–11.4).
 *
 * This tool is the dispatch handler registered under the `tickets.reply` key in
 * task 12.1; it is deliberately NOT wired into the {@see \App\Mcp\Registry\ToolDispatcher}
 * here. The transport ({@see \App\Http\Controllers\Mcp\McpController}) already
 * validates arguments against the tool's JSON Schema (required `ticket_id` and
 * `body`, optional boolean `is_internal`) before dispatch, so by the time
 * {@see self::handle()} runs the arguments are structurally valid.
 *
 * Failure handling mirrors the `create_ticket` tool and the transport's own
 * error shape (Req 11.1): a missing ticket yields a structured not-found error
 * and a {@see ValidationException} from the service yields a structured
 * field-level error. In BOTH cases the method RETURNS the error envelope rather
 * than throwing, and performs NO write — the service validates before it
 * persists, so a validation failure never reaches `TicketReply::create()`.
 */
final class ReplyTicketTool
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly McpBotUserResolver $botResolver,
    ) {
    }

    /**
     * Execute the `reply_ticket` tool.
     *
     * Resolves the ticket by its primary key (`ticket_id`); if it does not
     * exist, returns a structured not-found error and writes nothing (Req 11.1).
     * Otherwise it resolves the MCP bot actor and delegates to
     * {@see TicketService::reply()} with the `is_internal` flag (defaulting to
     * false), returning a compact summary of the created reply on success. A
     * validation failure is caught and surfaced as a structured field-level
     * error with no mutation (the service validates before persisting).
     *
     * @param  array<string, mixed>  $arguments  The validated `params.arguments`.
     * @return array<string, mixed> The compact reply summary, or a structured error envelope.
     */
    public function handle(array $arguments): array
    {
        $ticketId = (int) ($arguments['ticket_id'] ?? 0);

        $ticket = Ticket::find($ticketId);

        if ($ticket === null) {
            return $this->notFoundError($ticketId);
        }

        $isInternal = (bool) ($arguments['is_internal'] ?? false);

        try {
            $reply = $this->tickets->reply(
                $ticket,
                $arguments,
                $this->botResolver->resolve(),
                $isInternal,
            );
        } catch (ValidationException $e) {
            return $this->validationError($e);
        }

        $ticket->refresh();

        return [
            'reply_id' => $reply->id,
            'ticket_id' => $ticket->ticket_id,
            'is_internal' => (bool) $reply->is_internal,
            'ticket_status' => $ticket->status,
        ];
    }

    /**
     * Structured not-found error for a missing target ticket. No write is
     * performed; the shape mirrors the transport's JSON-RPC error `data`
     * (field-naming) so connectors see a consistent error envelope (Req 11.1).
     *
     * @return array<string, mixed>
     */
    private function notFoundError(int $ticketId): array
    {
        return [
            'error' => [
                'type' => 'not_found',
                'message' => sprintf('Ticket %d was not found.', $ticketId),
                'fields' => [
                    'ticket_id' => ['The selected ticket_id is invalid.'],
                ],
            ],
        ];
    }

    /**
     * Structured validation error surfaced from {@see TicketService::reply()}.
     * Preserves Laravel's field-level messages so the error shape matches the
     * transport's schema-validation envelope (Req 11.1). No write occurred.
     *
     * @return array<string, mixed>
     */
    private function validationError(ValidationException $e): array
    {
        return [
            'error' => [
                'type' => 'validation',
                'message' => 'The reply could not be saved because the input was invalid.',
                'fields' => $e->errors(),
            ],
        ];
    }
}
