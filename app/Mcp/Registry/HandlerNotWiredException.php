<?php

namespace App\Mcp\Registry;

use RuntimeException;

/**
 * Thrown by {@see ToolDispatcher::dispatch()} when a registered tool's handler
 * key has not yet been bound to a concrete callable.
 *
 * This is the explicit, structured signal for the "not-yet-wired" dispatch
 * seam. The MCP transport catches it and returns a JSON-RPC error (rather than
 * a raw stack trace) so a `tools/call` against a tool whose handler task 12.1
 * has not wired yet fails cleanly and auditable. Once 12.1 binds every handler
 * key via {@see ToolDispatcher::register()}, this exception is never thrown in
 * normal operation.
 */
class HandlerNotWiredException extends RuntimeException
{
    public function __construct(
        public readonly string $toolName,
        public readonly string $handlerKey,
    ) {
        parent::__construct(sprintf(
            'No handler is wired for tool "%s" (handler key "%s").',
            $toolName,
            $handlerKey,
        ));
    }
}
