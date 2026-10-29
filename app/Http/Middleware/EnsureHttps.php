<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject non-HTTPS requests on the MCP route group (Req 1.6).
 *
 * This is the FIRST middleware in the MCP stack so an insecure request is
 * rejected cheaply, before authentication, rate limiting, or any tool
 * dispatch runs. A Bearer access token must never travel over plaintext.
 *
 * ## Proxy awareness (cPanel / Cloudflare)
 *
 * In production the app is served behind a proxy (cPanel's web server and/or
 * Cloudflare) that terminates TLS and forwards the request to PHP over a
 * plaintext loopback connection. In that topology `$request->isSecure()`
 * alone can report `false` for a request the client made over HTTPS.
 *
 * Laravel already trusts all proxies (`trustProxies(at: '*')` in
 * bootstrap/app.php), so `Request::isSecure()` consults the forwarded headers
 * when the connection originates from a trusted proxy — meaning a genuine
 * HTTPS request arriving via the proxy is correctly treated as secure.
 *
 * As defence in depth this middleware ALSO honours the `X-Forwarded-Proto`
 * header directly, so the guard behaves correctly even if proxy trust is
 * reconfigured. The request is considered secure when EITHER the framework
 * reports a secure connection OR the forwarded protocol is `https`.
 *
 * On rejection it returns a structured JSON-RPC error (the MCP transport is
 * JSON-RPC) with HTTP 403 and no sensitive body content.
 */
class EnsureHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isSecure($request)) {
            return $this->insecure($request);
        }

        return $next($request);
    }

    /**
     * Whether the request reached us over HTTPS, honouring a trusted proxy's
     * `X-Forwarded-Proto` header (TLS terminates at the cPanel/Cloudflare edge).
     */
    private function isSecure(Request $request): bool
    {
        // Framework-level check: consults forwarded headers for trusted proxies.
        if ($request->isSecure()) {
            return true;
        }

        // Defence in depth: honour the forwarded protocol directly. The header
        // can be a comma-separated list (proto chain); the left-most value is
        // the protocol the original client used.
        $forwardedProto = $request->header('X-Forwarded-Proto');

        if (is_string($forwardedProto) && $forwardedProto !== '') {
            $clientProto = trim(strtolower(explode(',', $forwardedProto)[0]));

            if ($clientProto === 'https') {
                return true;
            }
        }

        return false;
    }

    /**
     * Structured rejection for a plaintext request — no body leakage.
     *
     * Echoes back the JSON-RPC request id when present so the connector can
     * correlate the error.
     */
    private function insecure(Request $request): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $request->input('id'),
            'error' => [
                'code' => -32600,
                'message' => 'HTTPS is required.',
            ],
        ], 403);
    }
}
