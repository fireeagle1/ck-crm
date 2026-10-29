<?php

namespace App\Mcp\Registry;

/**
 * The MCP tool allow-list — the single source of truth for both tool
 * advertisement (`tools/list`) and dispatch (`tools/call`).
 *
 * The registry maps each tool name to a {@see ToolDefinition} describing its
 * description, JSON input schema, required OAuth scope, kind (read/write), and
 * a stable handler identity. Because it is an allow-list, any tool name that is
 * not registered here is denied by default: {@see self::get()} returns null and
 * {@see self::has()} returns false, so no un-listed CRM operation (assets,
 * shop, domains, billing, user management, …) is reachable over MCP
 * (Req 2.2, 2.3). Scope-based authorization is enforced separately by the
 * scope-checking middleware using {@see ToolDefinition::$requiredScope}; this
 * registry never consults `is_admin`.
 *
 * Handlers are referenced loosely by a stable key ({@see ToolDefinition::$handler})
 * rather than bound to concrete callables here. Final wiring (task 12.1)
 * connects each key to its read query service or ticket write tool without
 * needing to restructure the registry. The three write tools
 * (`create_ticket`, `update_ticket`, `reply_ticket`) do not have concrete
 * handlers yet; their keys are placeholders that resolve during that wiring.
 *
 * @see \App\Mcp\Registry\ToolDefinition
 */
final class ToolRegistry
{
    /**
     * The OAuth scope set backing the registered tools. Mirrors the fixed set
     * registered with Passport (`customers:read`, `invoices:read`,
     * `projects:read`, `services:read`, `tickets:read`, `tickets:write`).
     */
    public const SCOPE_CUSTOMERS_READ = 'customers:read';

    public const SCOPE_INVOICES_READ = 'invoices:read';

    public const SCOPE_PROJECTS_READ = 'projects:read';

    public const SCOPE_SERVICES_READ = 'services:read';

    public const SCOPE_TICKETS_READ = 'tickets:read';

    public const SCOPE_TICKETS_WRITE = 'tickets:write';

    public const SCOPE_APPS_READ = 'apps:read';

    public const SCOPE_APPS_WRITE = 'apps:write';

    public const SCOPE_KNOWLEDGE_READ = 'knowledge:read';

    /**
     * The registered tools, keyed by tool name.
     *
     * @var array<string, ToolDefinition>
     */
    private array $tools;

    public function __construct()
    {
        $this->tools = $this->buildDefinitions();
    }

    /**
     * Look up a single tool by name.
     *
     * Returns null for any name that is not registered — the default-deny
     * behaviour callers (middleware, dispatcher) rely on.
     */
    public function get(string $name): ?ToolDefinition
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Whether a tool with the given name is registered (and therefore allowed).
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * All registered tools, keyed by tool name.
     *
     * @return array<string, ToolDefinition>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * All registered tool names.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->tools);
    }

    /**
     * Build the central allow-list.
     *
     * @return array<string, ToolDefinition>
     */
    private function buildDefinitions(): array
    {
        $definitions = [
            // --- Customers (read) -------------------------------------------
            new ToolDefinition(
                name: 'get_customer',
                description: 'Read-only. Fetch a single customer by company_id and return its '
                    .'contact and address details. Does not modify any data.',
                inputSchema: $this->object(
                    properties: [
                        'company_id' => $this->integer('The customer primary key (company_id).'),
                    ],
                    required: ['company_id'],
                ),
                requiredScope: self::SCOPE_CUSTOMERS_READ,
                kind: ToolDefinition::KIND_READ,
                handler: 'customers.get',
            ),
            new ToolDefinition(
                name: 'search_customers',
                description: 'Read-only. Search customers by name and return a paginated list of '
                    .'contact and address details. Does not modify any data.',
                inputSchema: $this->object(
                    properties: [
                        'name' => $this->string('Optional name term matched against company, contact, or phone.'),
                        'page' => $this->page(),
                        'per_page' => $this->perPage(),
                    ],
                ),
                requiredScope: self::SCOPE_CUSTOMERS_READ,
                kind: ToolDefinition::KIND_READ,
                handler: 'customers.search',
            ),

            // --- Invoices (read) --------------------------------------------
            new ToolDefinition(
                name: 'get_invoice',
                description: 'Read-only. Fetch a single invoice by invoice_id, including its '
                    .'hosted invoice URL. Does not modify any data.',
                inputSchema: $this->object(
                    properties: [
                        'invoice_id' => $this->integer('The invoice primary key (invoice_id).'),
                    ],
                    required: ['invoice_id'],
                ),
                requiredScope: self::SCOPE_INVOICES_READ,
                kind: ToolDefinition::KIND_READ,
                handler: 'invoices.get',
            ),
            new ToolDefinition(
                name: 'list_invoices',
                description: 'Read-only. List invoices (collectable by default), optionally '
                    .'filtered by company_id and invoice_status. Does not modify any data.',
                inputSchema: $this->object(
                    properties: [
                        'company_id' => $this->integer('Optional customer filter (company_id).'),
                        'invoice_status' => $this->string('Optional invoice status filter.'),
                        'page' => $this->page(),
                        'per_page' => $this->perPage(),
                    ],
                ),
                requiredScope: self::SCOPE_INVOICES_READ,
                kind: ToolDefinition::KIND_READ,
                handler: 'invoices.list',
            ),

            // --- Projects (read) --------------------------------------------
            new ToolDefinition(
                name: 'get_project',
                description: 'Read-only. Fetch a single project by id, including its computed '
                    .'progress percentage. Does not modify any data.',
                inputSchema: $this->object(
                    properties: [
                        'id' => $this->integer('The project primary key (id).'),
                    ],
                    required: ['id'],
                ),
                requiredScope: self::SCOPE_PROJECTS_READ,
                kind: ToolDefinition::KIND_READ,
                handler: 'projects.get',
            ),
            new ToolDefinition(
                name: 'list_projects',
                description: 'Read-only. List projects, optionally filtered by company_id and '
                    .'status. Does not modify any data.',
                inputSchema: $this->object(
                    properties: [
                        'company_id' => $this->integer('Optional customer filter (company_id).'),
                        'status' => $this->string('Optional project status filter.'),
                        'page' => $this->page(),
                        'per_page' => $this->perPage(),
                    ],
                ),
                requiredScope: self::SCOPE_PROJECTS_READ,
                kind: ToolDefinition::KIND_READ,
                handler: 'projects.list',
            ),

            // --- Services (read) --------------------------------------------
            new ToolDefinition(
                name: 'get_service',
                description: 'Read-only. Fetch a single service by service_id. Credentials and '
                    .'billing identifiers are never returned. Does not modify any data.',
                inputSchema: $this->object(
                    properties: [
                        'service_id' => $this->integer('The service primary key (service_id).'),
                    ],
                    required: ['service_id'],
                ),
                requiredScope: self::SCOPE_SERVICES_READ,
                kind: ToolDefinition::KIND_READ,
                handler: 'services.get',
            ),
            new ToolDefinition(
                name: 'list_services',
                description: 'Read-only. List services, optionally filtered by company_id and '
                    .'status. Credentials and billing identifiers are never returned. '
                    .'Does not modify any data.',
                inputSchema: $this->object(
                    properties: [
                        'company_id' => $this->integer('Optional customer filter (company_id).'),
                        'status' => $this->string('Optional service status filter.'),
                        'page' => $this->page(),
                        'per_page' => $this->perPage(),
                    ],
                ),
                requiredScope: self::SCOPE_SERVICES_READ,
                kind: ToolDefinition::KIND_READ,
                handler: 'services.list',
            ),

            // --- Web Apps (read) --------------------------------------------
            new ToolDefinition(
                name: 'get_app',
                description: 'Read-only. Fetch a single web app by id, returning its technical '
                    .'metadata (repo_url, hosting_location, codebase_info) and the linked '
                    .'service/customer identifiers. Does not modify any data.',
                inputSchema: $this->object(
                    properties: [
                        'id' => $this->integer('The web app primary key (id).'),
                    ],
                    required: ['id'],
                ),
                requiredScope: self::SCOPE_APPS_READ,
                kind: ToolDefinition::KIND_READ,
                handler: 'apps.get',
            ),
            new ToolDefinition(
                name: 'list_apps',
                description: 'Read-only. List web apps, optionally filtered by service_id and/or '
                    .'company_id (the owning customer), returning paginated technical metadata. '
                    .'Does not modify any data.',
                inputSchema: $this->object(
                    properties: [
                        'service_id' => $this->integer('Optional filter: the app on a single service (service_id).'),
                        'company_id' => $this->integer('Optional customer filter (company_id).'),
                        'page' => $this->page(),
                        'per_page' => $this->perPage(),
                    ],
                ),
                requiredScope: self::SCOPE_APPS_READ,
                kind: ToolDefinition::KIND_READ,
                handler: 'apps.list',
            ),

            // --- Web Apps (write) -------------------------------------------
            new ToolDefinition(
                name: 'update_app',
                description: 'WRITE operation. Updates a web app\'s technical metadata. Only the '
                    .'provided fields are changed; omitted fields are left untouched. Billing, '
                    .'status, and the customer-facing name live on the linked service and are '
                    .'never changed here. This changes CRM data. Requires the apps:write scope.',
                inputSchema: $this->object(
                    properties: [
                        'id' => $this->integer('The web app to update (id). Must exist.'),
                        'repo_url' => $this->string('Optional repository URL (http/https).'),
                        'hosting_location' => $this->string('Optional hosting/storage location.'),
                        'codebase_info' => $this->string('Optional free-text codebase notes (framework, language, architecture).'),
                    ],
                    required: ['id'],
                ),
                requiredScope: self::SCOPE_APPS_WRITE,
                kind: ToolDefinition::KIND_WRITE,
                handler: 'apps.update',
            ),

            // --- Tickets (read) ---------------------------------------------
            new ToolDefinition(
                name: 'get_ticket',
                description: 'Read-only. Fetch a single ticket by ticket_id with full detail: '
                    .'description, customer/service/asset references, all replies, and the '
                    .'activity history. Does not modify any data.',
                inputSchema: $this->object(
                    properties: [
                        'ticket_id' => $this->integer('The ticket primary key (ticket_id).'),
                    ],
                    required: ['ticket_id'],
                ),
                requiredScope: self::SCOPE_TICKETS_READ,
                kind: ToolDefinition::KIND_READ,
                handler: 'tickets.get',
            ),
            new ToolDefinition(
                name: 'search_tickets',
                description: 'Read-only. Search tickets by status, priority, company_id, and a '
                    .'free-text term, returning paginated summaries. Does not modify any data.',
                inputSchema: $this->object(
                    properties: [
                        'status' => $this->string('Optional status filter ("open" expands to the open set).'),
                        'priority' => $this->string('Optional priority filter.'),
                        'company_id' => $this->integer('Optional customer filter (company_id).'),
                        'search' => $this->string('Optional free-text term matched against subject, id, or customer name.'),
                        'page' => $this->page(),
                        'per_page' => $this->perPage(),
                    ],
                ),
                requiredScope: self::SCOPE_TICKETS_READ,
                kind: ToolDefinition::KIND_READ,
                handler: 'tickets.search',
            ),

            // --- Knowledgebase (read) ---------------------------------------
            new ToolDefinition(
                name: 'get_article',
                description: 'Read-only. Fetch a single knowledgebase article by article_id, '
                    .'including its full content body. Does not modify any data.',
                inputSchema: $this->object(
                    properties: [
                        'article_id' => $this->integer('The knowledgebase article primary key (article_id).'),
                    ],
                    required: ['article_id'],
                ),
                requiredScope: self::SCOPE_KNOWLEDGE_READ,
                kind: ToolDefinition::KIND_READ,
                handler: 'knowledge.get',
            ),
            new ToolDefinition(
                name: 'search_articles',
                description: 'Read-only. Search knowledgebase articles by a free-text term '
                    .'(matched against title and content), optionally filtered by category and '
                    .'company_id, returning paginated summaries (without the full body). Does '
                    .'not modify any data.',
                inputSchema: $this->object(
                    properties: [
                        'search' => $this->string('Optional free-text term matched against the article title and content.'),
                        'category' => $this->string('Optional category filter (exact match).'),
                        'company_id' => $this->integer('Optional customer filter (company_id) for company-specific articles.'),
                        'page' => $this->page(),
                        'per_page' => $this->perPage(),
                    ],
                ),
                requiredScope: self::SCOPE_KNOWLEDGE_READ,
                kind: ToolDefinition::KIND_READ,
                handler: 'knowledge.search',
            ),

            // --- Tickets (write) --------------------------------------------
            // These three are the ONLY state-changing tools. Their handlers are
            // implemented in task 10 and wired by key in task 12.1; the handler
            // identities below are stable placeholders until then.
            new ToolDefinition(
                name: 'create_ticket',
                description: 'WRITE operation. Creates a new support ticket for a customer and may '
                    .'notify the customer. This changes CRM data. Requires the tickets:write scope.',
                inputSchema: $this->object(
                    properties: [
                        'company_id' => $this->integer('The customer the ticket belongs to (company_id). Must exist.'),
                        'subject' => $this->string('Ticket subject (max 255 characters).'),
                        'description' => $this->string('Ticket description / body.'),
                        'priority' => $this->enum(['Low', 'Normal', 'High', 'Critical'], 'Ticket priority.'),
                        'ticket_type' => $this->enum(['Incident', 'Service Request'], 'Ticket type.'),
                        'request_category' => $this->string('Optional request category (max 100 characters).'),
                        'user_id' => $this->integer('Optional assignee / requesting user id. Must exist.'),
                        'asset_id' => $this->integer('Optional related asset id (cmdb.device_id). Must exist.'),
                        'service_id' => $this->integer('Optional related service id. Must exist.'),
                        'notify_customer' => $this->boolean('Whether to email the customer that the ticket was opened.'),
                    ],
                    required: ['company_id', 'subject', 'description', 'priority', 'ticket_type'],
                ),
                requiredScope: self::SCOPE_TICKETS_WRITE,
                kind: ToolDefinition::KIND_WRITE,
                handler: 'tickets.create',
            ),
            new ToolDefinition(
                name: 'update_ticket',
                description: 'WRITE operation. Updates an existing ticket (status, priority, type, '
                    .'assignee, related asset/service, or due date) and may notify the customer. '
                    .'This changes CRM data. Requires the tickets:write scope.',
                inputSchema: $this->object(
                    properties: [
                        'ticket_id' => $this->integer('The ticket to update (ticket_id). Must exist.'),
                        'status' => $this->enum(['Open', 'Pending', 'In Progress', 'Closed'], 'Optional new status.'),
                        'priority' => $this->enum(['Low', 'Normal', 'High', 'Critical'], 'Optional new priority.'),
                        'ticket_type' => $this->enum(['Incident', 'Service Request'], 'Optional new type.'),
                        'asset_id' => $this->integer('Optional related asset id (cmdb.device_id). Must exist.'),
                        'service_id' => $this->integer('Optional related service id. Must exist.'),
                        'user_id' => $this->integer('Optional assignee user id. Must exist.'),
                        'due_at' => $this->string('Optional due date/time (ISO 8601).'),
                    ],
                    required: ['ticket_id'],
                ),
                requiredScope: self::SCOPE_TICKETS_WRITE,
                kind: ToolDefinition::KIND_WRITE,
                handler: 'tickets.update',
            ),
            new ToolDefinition(
                name: 'reply_ticket',
                description: 'WRITE operation. Adds a reply to an existing ticket. Non-internal '
                    .'replies email the customer and may reopen a closed ticket; internal notes '
                    .'do not. This changes CRM data. Requires the tickets:write scope.',
                inputSchema: $this->object(
                    properties: [
                        'ticket_id' => $this->integer('The ticket to reply to (ticket_id). Must exist.'),
                        'body' => $this->string('The reply body.'),
                        'is_internal' => $this->boolean('Whether the reply is an internal note (not emailed to the customer).'),
                    ],
                    required: ['ticket_id', 'body'],
                ),
                requiredScope: self::SCOPE_TICKETS_WRITE,
                kind: ToolDefinition::KIND_WRITE,
                handler: 'tickets.reply',
            ),
        ];

        $map = [];
        foreach ($definitions as $definition) {
            $map[$definition->name] = $definition;
        }

        return $map;
    }

    // --- JSON Schema construction helpers -------------------------------------

    /**
     * Build a JSON Schema object node.
     *
     * @param  array<string, array<string, mixed>>  $properties
     * @param  array<int, string>  $required
     * @return array<string, mixed>
     */
    private function object(array $properties, array $required = []): array
    {
        $schema = [
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => false,
        ];

        if ($required !== []) {
            $schema['required'] = array_values($required);
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function integer(string $description): array
    {
        return ['type' => 'integer', 'description' => $description];
    }

    /**
     * @return array<string, mixed>
     */
    private function string(string $description): array
    {
        return ['type' => 'string', 'description' => $description];
    }

    /**
     * @return array<string, mixed>
     */
    private function boolean(string $description): array
    {
        return ['type' => 'boolean', 'description' => $description];
    }

    /**
     * @param  array<int, string>  $values
     * @return array<string, mixed>
     */
    private function enum(array $values, string $description): array
    {
        return ['type' => 'string', 'enum' => array_values($values), 'description' => $description];
    }

    /**
     * Shared `page` parameter schema (1-based page number).
     *
     * @return array<string, mixed>
     */
    private function page(): array
    {
        return [
            'type' => 'integer',
            'minimum' => 1,
            'description' => '1-based page number (defaults to the first page).',
        ];
    }

    /**
     * Shared `per_page` parameter schema. The effective page size is capped to
     * 100 server-side regardless of the requested value.
     *
     * @return array<string, mixed>
     */
    private function perPage(): array
    {
        return [
            'type' => 'integer',
            'minimum' => 1,
            'maximum' => 100,
            'description' => 'Requested page size; the server caps the effective size at 100.',
        ];
    }
}
