<?php

namespace App\Mcp\Registry;

/**
 * The dispatch seam between the MCP transport and concrete tool handlers.
 *
 * The {@see ToolRegistry} describes WHAT tools exist (name, schema, scope,
 * handler key); this dispatcher resolves a {@see ToolDefinition}'s stable
 * handler key to the concrete callable that actually runs the tool and returns
 * its result payload.
 *
 * Handlers are registered by key via {@see self::register()}. A handler is any
 * callable with the signature:
 *
 *     fn (array $arguments): mixed
 *
 * where `$arguments` is the validated `params.arguments` map from a
 * `tools/call` request and the return value is the tool's result payload (a
 * read DTO array, a ticket summary, etc.). The transport wraps whatever is
 * returned into the MCP `{ content: [...] }` result shape.
 *
 * WIRING (task 12.1): this class is the single plug-in point for connecting
 * every registered tool to its read query service or ticket write tool. Until
 * that wiring lands, no handler keys are registered and {@see self::dispatch()}
 * throws {@see HandlerNotWiredException}, which the transport renders as a
 * structured JSON-RPC error rather than a stack trace. Registering a handler
 * in 12.1 therefore requires no change to the transport or the registry — only
 * a call to {@see self::register()} per handler key.
 */
class ToolDispatcher
{
    /**
     * Concrete handlers keyed by the stable handler identity used in the
     * registry ({@see ToolDefinition::$handler}).
     *
     * @var array<string, callable>
     */
    private array $handlers = [];

    /**
     * Bind a concrete callable to a handler key.
     *
     * Task 12.1 calls this once per registered tool to wire the read query
     * services and ticket write tools. Re-registering a key overwrites the
     * previous binding.
     *
     * @param  callable  $handler  fn (array $arguments): mixed
     */
    public function register(string $handlerKey, callable $handler): void
    {
        $this->handlers[$handlerKey] = $handler;
    }

    /**
     * Whether a concrete handler is bound for the given key.
     */
    public function has(string $handlerKey): bool
    {
        return isset($this->handlers[$handlerKey]);
    }

    /**
     * Invoke the concrete handler for a tool definition with validated args.
     *
     * @param  array<string, mixed>  $arguments  The validated `params.arguments`.
     * @return mixed The tool's result payload, wrapped by the transport.
     *
     * @throws HandlerNotWiredException when no handler is bound for the key.
     */
    public function dispatch(ToolDefinition $definition, array $arguments): mixed
    {
        $handlerKey = $definition->handler;

        if (! isset($this->handlers[$handlerKey])) {
            throw new HandlerNotWiredException($definition->name, $handlerKey);
        }

        return ($this->handlers[$handlerKey])($arguments);
    }
}
