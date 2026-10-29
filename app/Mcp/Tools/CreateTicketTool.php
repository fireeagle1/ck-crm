<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpBotUserResolver;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Validation\ValidationException;

/**
 * MCP write tool: `create_ticket` (scope `tickets:write`).
 *
 * A thin adapter over {@see TicketService::create()} that logs a new incident
 * on behalf of ChatGPT. It performs no logic of its own beyond wiring the MCP
 * call to the shared service: it resolves the dedicated, least-privilege MCP
 * bot user ({@see McpBotUserResolver}) as the write actor, forwards the
 * validated `tools/call` arguments and the `notify_customer` flag, and returns
 * a compact summary of the created ticket.
 *
 * Validation parity and no-mutation-on-invalid (Req 9.1, 9.2): the business
 * rules — `company_id` must exist, `subject`/`description`/`priority`/
 * `ticket_type` are required with the same enum constraints as the admin create
 * path, and `user_id`/`asset_id`/`service_id`/`request_category` are optional —
 * are enforced by {@see TicketService::create()} via Laravel's validator, which
 * runs BEFORE any row is written. When they fail it throws
 * {@see ValidationException}; this tool lets that exception propagate unchanged
 * so the MCP transport renders a structured JSON-RPC error naming the invalid
 * fields, and because the throw happens before persistence no ticket, reply, or
 * activity is created.
 *
 * Side-effect parity (Req 9.3, 9.4): persisting with `status = 'Open'`,
 * notifying admins, and the conditional customer "ticket opened" email are all
 * owned by {@see TicketService::create()} and shared verbatim with the admin
 * API, so an MCP-originated create behaves identically to an admin create
 * (differing only in the recorded actor and the `source` column).
 *
 * Result (Req 9.5): on success the tool returns the persisted `ticket_id` plus
 * a compact summary. Full models are never serialised into the response.
 *
 * The handler is exposed through {@see self::handle()} with the dispatcher's
 * `fn (array $arguments): array` shape and bound to the registry handler key
 * `tickets.create` during final wiring (task 12.1); this class does not
 * register itself.
 *
 * @see \App\Services\TicketService::create()
 * @see \App\Mcp\Support\McpBotUserResolver
 * @see \App\Mcp\Registry\ToolRegistry
 */
final class CreateTicketTool
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly McpBotUserResolver $botUserResolver,
    ) {
    }

    /**
     * Create a ticket from a validated `tools/call` argument map.
     *
     * @param  array<string, mixed>  $arguments  The validated `params.arguments`
     *                                            (company_id, subject, description,
     *                                            priority, ticket_type, and the
     *                                            optional user_id/asset_id/service_id/
     *                                            request_category/notify_customer).
     * @return array<string, mixed> A compact success summary: ticket_id, subject,
     *                               status, priority, ticket_type, company_id.
     *
     * @throws ValidationException When the business rules reject the input. The
     *                             exception propagates to the transport, which
     *                             renders a structured field-naming error; no
     *                             ticket is written in that case.
     */
    public function handle(array $arguments): array
    {
        $notifyCustomer = (bool) ($arguments['notify_customer'] ?? false);

        $ticket = $this->tickets->create(
            $arguments,
            $this->botUserResolver->resolve(),
            $notifyCustomer,
        );

        return $this->summarise($ticket);
    }

    /**
     * Build the compact, allow-listed success payload for a created ticket.
     *
     * @return array<string, mixed>
     */
    private function summarise(Ticket $ticket): array
    {
        return [
            'ticket_id' => $ticket->ticket_id,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'ticket_type' => $ticket->ticket_type,
            'company_id' => $ticket->company_id,
        ];
    }
}
