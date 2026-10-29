<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MCP Per-Token Rate Limit
    |--------------------------------------------------------------------------
    |
    | The maximum number of MCP requests allowed per minute for a single
    | Passport access token (falling back to the authenticated user id, then
    | the client IP). Exceeding this limit returns a standard
    | `429 Too Many Requests` response with `Retry-After` and `X-RateLimit-*`
    | headers. Tune via the MCP_RATE_LIMIT environment variable.
    |
    */

    'rate_limit' => (int) env('MCP_RATE_LIMIT', 60),

];
