<?php

namespace App\Mcp\Registry;

/**
 * Immutable description of a single MCP tool.
 *
 * A {@see ToolDefinition} is the registry's unit of truth for one tool. It
 * carries everything the transport needs to advertise the tool (`tools/list`)
 * and everything the dispatcher needs to invoke it (`tools/call`):
 *
 *  - {@see $name}          the stable MCP tool name (e.g. `get_customer`).
 *  - {@see $description}   human-readable text shown to the model; read tools
 *                          and the three ticket write tools are described
 *                          distinctly so a caller can tell a safe lookup from a
 *                          state-changing operation (Req 3.3).
 *  - {@see $inputSchema}   a JSON Schema (as a PHP array) describing the tool's
 *                          parameters; used to advertise and validate input.
 *  - {@see $requiredScope} the single OAuth scope a token must carry to invoke
 *                          this tool; authorization consults only this scope,
 *                          never `is_admin` (Req 2.1).
 *  - {@see $kind}          whether the tool reads or writes, used only for
 *                          advertisement/metadata, never for authorization.
 *  - {@see $handler}       a stable handler identity (a handler key). The
 *                          registry intentionally references handlers loosely
 *                          by key rather than binding a concrete callable here,
 *                          so task 12.1 can wire the read query services and the
 *                          ticket write tools to these keys without
 *                          restructuring the registry.
 *
 * The write-tool handlers (`create_ticket`, `update_ticket`, `reply_ticket`)
 * do not exist yet; their handler keys are placeholders that resolve to real
 * handlers during final wiring.
 */
final class ToolDefinition
{
    public const KIND_READ = 'read';

    public const KIND_WRITE = 'write';

    /**
     * @param  string  $name           Stable MCP tool name.
     * @param  string  $description    Human-readable description (read vs write distinguished).
     * @param  array<string, mixed>  $inputSchema  JSON Schema for the tool's parameters.
     * @param  string  $requiredScope  The OAuth scope required to invoke the tool.
     * @param  string  $kind           One of {@see self::KIND_READ} / {@see self::KIND_WRITE}.
     * @param  string  $handler        Stable handler identity (handler key) wired at dispatch time.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
        public readonly string $requiredScope,
        public readonly string $kind,
        public readonly string $handler,
    ) {
    }

    /**
     * Whether this tool mutates state (one of the three ticket write tools).
     */
    public function isWrite(): bool
    {
        return $this->kind === self::KIND_WRITE;
    }

    /**
     * Whether this tool is a read-only lookup.
     */
    public function isRead(): bool
    {
        return $this->kind === self::KIND_READ;
    }

    /**
     * Render this definition as the MCP `tools/list` advertisement shape:
     * `{ name, description, inputSchema }`.
     *
     * The required scope, kind, and handler identity are internal registry
     * concerns and are intentionally omitted from the advertised payload.
     *
     * @return array{name: string, description: string, inputSchema: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema,
        ];
    }
}
