<?php

namespace App\Providers;

use App\Mcp\Query\CustomerAppQueryService;
use App\Mcp\Query\CustomerQueryService;
use App\Mcp\Query\InvoiceQueryService;
use App\Mcp\Query\KnowledgeQueryService;
use App\Mcp\Query\ProjectsQueryService;
use App\Mcp\Query\ServicesQueryService;
use App\Mcp\Query\TicketQueryService;
use App\Mcp\Registry\ToolDispatcher;
use App\Mcp\Tools\CreateTicketTool;
use App\Mcp\Tools\ReplyTicketTool;
use App\Mcp\Tools\UpdateCustomerAppTool;
use App\Mcp\Tools\UpdateTicketTool;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the MCP tool registry's handler keys to their concrete handlers
 * (task 12.1).
 *
 * The {@see \App\Mcp\Registry\ToolRegistry} describes WHAT tools exist and maps
 * each to a stable handler key ({@see \App\Mcp\Registry\ToolDefinition::$handler}).
 * The {@see ToolDispatcher} is the dispatch seam that resolves a handler key to
 * the concrete callable that actually runs the tool. This provider binds every
 * one of the registered handler keys onto the shared {@see ToolDispatcher}
 * singleton so that a `tools/call` for any tool dispatches end-to-end through
 * the full middleware stack with no orphaned code and no
 * {@see \App\Mcp\Registry\HandlerNotWiredException} in normal operation
 * (Req 2.3, 3.1).
 *
 * Each handler is `fn (array $arguments): mixed`, matching the dispatcher's
 * contract, and is resolved lazily from the container inside the closure so
 * the query services and write tools are constructed only when a tool is
 * actually invoked:
 *
 *  - Read handlers map the validated `params.arguments` map to the concrete
 *    query-service method parameters. The `get_*` tools return a single DTO
 *    array or, when the record does not exist, a structured not-found error
 *    envelope (the same `{ error: { ... } }` shape the ticket write tools use
 *    to signal a missing ticket) so the connector receives an actionable
 *    message rather than a bare null.
 *  - Write handlers resolve the corresponding tool class and delegate to its
 *    `handle($arguments)` method, which attributes the write to the MCP bot
 *    actor and returns a compact summary (Req 9.5, 10.2, 11.5).
 *
 * The dispatcher is a singleton registered in {@see AppServiceProvider::register()},
 * so the instance wired here is the same one the transport
 * ({@see \App\Http\Controllers\Mcp\McpController}) resolves and dispatches
 * through.
 */
class McpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bind every registered tool's handler key to its concrete handler.
     */
    public function boot(): void
    {
        /** @var ToolDispatcher $dispatcher */
        $dispatcher = $this->app->make(ToolDispatcher::class);

        $this->wireReadHandlers($dispatcher);
        $this->wireWriteHandlers($dispatcher);
    }

    /**
     * Wire the read tools to their query services.
     */
    private function wireReadHandlers(ToolDispatcher $dispatcher): void
    {
        // --- Customers (customers:read) --------------------------------------
        $dispatcher->register('customers.get', function (array $args) {
            $result = $this->app->make(CustomerQueryService::class)
                ->getCustomer($args['company_id']);

            return $result ?? $this->notFound('customer', 'company_id', $args['company_id'] ?? null);
        });

        $dispatcher->register('customers.search', fn (array $args) => $this->app
            ->make(CustomerQueryService::class)
            ->searchCustomers(
                $args['name'] ?? null,
                $this->intOrNull($args['page'] ?? null),
                $this->intOrNull($args['per_page'] ?? null),
            ));

        // --- Invoices (invoices:read) ----------------------------------------
        $dispatcher->register('invoices.get', function (array $args) {
            $result = $this->app->make(InvoiceQueryService::class)
                ->getInvoice((int) $args['invoice_id']);

            return $result ?? $this->notFound('invoice', 'invoice_id', $args['invoice_id'] ?? null);
        });

        $dispatcher->register('invoices.list', fn (array $args) => $this->app
            ->make(InvoiceQueryService::class)
            ->listInvoices(
                $this->intOrNull($args['company_id'] ?? null),
                $args['invoice_status'] ?? null,
                $this->intOrNull($args['page'] ?? null),
                $this->intOrNull($args['per_page'] ?? null),
            ));

        // --- Projects (projects:read) ----------------------------------------
        $dispatcher->register('projects.get', function (array $args) {
            $result = $this->app->make(ProjectsQueryService::class)
                ->getProject($args['id']);

            return $result ?? $this->notFound('project', 'id', $args['id'] ?? null);
        });

        $dispatcher->register('projects.list', fn (array $args) => $this->app
            ->make(ProjectsQueryService::class)
            ->listProjects(
                $this->intOrNull($args['company_id'] ?? null),
                $args['status'] ?? null,
                $this->intOrNull($args['page'] ?? null),
                $this->intOrNull($args['per_page'] ?? null),
            ));

        // --- Services (services:read) ----------------------------------------
        $dispatcher->register('services.get', function (array $args) {
            $result = $this->app->make(ServicesQueryService::class)
                ->getService($args['service_id']);

            return $result ?? $this->notFound('service', 'service_id', $args['service_id'] ?? null);
        });

        $dispatcher->register('services.list', fn (array $args) => $this->app
            ->make(ServicesQueryService::class)
            ->listServices(
                $this->intOrNull($args['company_id'] ?? null),
                $args['status'] ?? null,
                $this->intOrNull($args['page'] ?? null),
                $this->intOrNull($args['per_page'] ?? null),
            ));

        // --- Web Apps (apps:read) --------------------------------------------
        $dispatcher->register('apps.get', function (array $args) {
            $result = $this->app->make(CustomerAppQueryService::class)
                ->getCustomerApp($args['id']);

            return $result ?? $this->notFound('web app', 'id', $args['id'] ?? null);
        });

        $dispatcher->register('apps.list', fn (array $args) => $this->app
            ->make(CustomerAppQueryService::class)
            ->listCustomerApps(
                $this->intOrNull($args['service_id'] ?? null),
                $this->intOrNull($args['company_id'] ?? null),
                $this->intOrNull($args['page'] ?? null),
                $this->intOrNull($args['per_page'] ?? null),
            ));

        // --- Tickets (tickets:read) ------------------------------------------
        $dispatcher->register('tickets.get', function (array $args) {
            $result = $this->app->make(TicketQueryService::class)
                ->getTicket((int) $args['ticket_id']);

            return $result ?? $this->notFound('ticket', 'ticket_id', $args['ticket_id'] ?? null);
        });

        $dispatcher->register('tickets.search', fn (array $args) => $this->app
            ->make(TicketQueryService::class)
            ->searchTickets(
                $args['status'] ?? null,
                $args['priority'] ?? null,
                $this->intOrNull($args['company_id'] ?? null),
                $args['search'] ?? null,
                $this->intOrNull($args['page'] ?? null),
                $this->intOrNull($args['per_page'] ?? null),
            ));

        // --- Knowledgebase (knowledge:read) ----------------------------------
        $dispatcher->register('knowledge.get', function (array $args) {
            $result = $this->app->make(KnowledgeQueryService::class)
                ->getArticle($args['article_id']);

            return $result ?? $this->notFound('knowledgebase article', 'article_id', $args['article_id'] ?? null);
        });

        $dispatcher->register('knowledge.search', fn (array $args) => $this->app
            ->make(KnowledgeQueryService::class)
            ->searchArticles(
                $args['search'] ?? null,
                $args['category'] ?? null,
                $this->intOrNull($args['company_id'] ?? null),
                $this->intOrNull($args['page'] ?? null),
                $this->intOrNull($args['per_page'] ?? null),
            ));
    }

    /**
     * Wire the three ticket write tools to their tool classes.
     */
    private function wireWriteHandlers(ToolDispatcher $dispatcher): void
    {
        $dispatcher->register('tickets.create', fn (array $args) => $this->app
            ->make(CreateTicketTool::class)
            ->handle($args));

        $dispatcher->register('tickets.update', fn (array $args) => $this->app
            ->make(UpdateTicketTool::class)
            ->handle($args));

        $dispatcher->register('tickets.reply', fn (array $args) => $this->app
            ->make(ReplyTicketTool::class)
            ->handle($args));

        $dispatcher->register('apps.update', fn (array $args) => $this->app
            ->make(UpdateCustomerAppTool::class)
            ->handle($args));
    }

    /**
     * A structured not-found error envelope for a `get_*` read tool whose target
     * record does not exist.
     *
     * Mirrors the `{ error: { ... } }` shape the ticket write tools return for a
     * missing ticket so the connector sees a consistent, actionable error rather
     * than a bare null. No mutation ever occurs on a read.
     *
     * @return array<string, mixed>
     */
    private function notFound(string $resource, string $field, mixed $value): array
    {
        return [
            'error' => [
                'type' => 'not_found',
                'message' => sprintf(
                    'No %s was found for %s "%s".',
                    $resource,
                    $field,
                    is_scalar($value) ? (string) $value : '',
                ),
                'fields' => [
                    $field => [sprintf('The selected %s does not exist.', $field)],
                ],
            ],
        ];
    }

    /**
     * Coerce an optional pagination/filter argument to a positive-aware int or
     * null, so a missing argument leaves the query service's own default in
     * place rather than forcing a zero.
     */
    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
