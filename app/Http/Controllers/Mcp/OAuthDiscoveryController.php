<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Providers\PassportServiceProvider;
use Illuminate\Http\JsonResponse;

/**
 * OAuth 2.0 / MCP discovery metadata.
 *
 * ChatGPT's remote MCP connector performs *automatic* authorization-server
 * discovery before it ever hits /oauth/authorize: it probes the well-known
 * metadata documents and reads the endpoint URLs, supported grant types, PKCE
 * methods and scopes from them. Passport registers the /oauth/* endpoints but
 * ships no discovery documents, so that automatic discovery returned 404 and
 * the endpoints had to be entered by hand.
 *
 * These endpoints publish:
 *   - RFC 8414  OAuth 2.0 Authorization Server Metadata
 *     (/.well-known/oauth-authorization-server)
 *   - RFC 9728  OAuth 2.0 Protected Resource Metadata
 *     (/.well-known/oauth-protected-resource) — points the connector at the
 *     authorization server that guards the /api/mcp resource.
 *
 * They are intentionally public and unauthenticated (metadata only, no
 * secrets) and describe the public PKCE client flow the connector uses:
 * authorization_code + refresh_token grants, S256 PKCE, no client secret.
 */
class OAuthDiscoveryController extends Controller
{
    /**
     * RFC 8414 — Authorization Server Metadata.
     *
     * The issuer and all endpoint URLs are derived from the request host via
     * url()/route() so the document is always correct for whatever domain it is
     * served from (e.g. https://app.ckenterprises.co.uk) without hardcoding.
     */
    public function authorizationServer(): JsonResponse
    {
        $issuer = rtrim(url('/'), '/');

        return response()->json([
            'issuer' => $issuer,
            'authorization_endpoint' => route('passport.authorizations.authorize'),
            'token_endpoint' => route('passport.token'),
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => array_keys(PassportServiceProvider::MCP_SCOPES),
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * RFC 9728 — Protected Resource Metadata.
     *
     * Advertises the MCP resource (/api/mcp) and the authorization server that
     * protects it, so the connector can walk from the resource to the correct
     * authorization server automatically.
     */
    public function protectedResource(): JsonResponse
    {
        $issuer = rtrim(url('/'), '/');

        return response()->json([
            'resource' => $issuer.'/api/mcp',
            'authorization_servers' => [$issuer],
            'scopes_supported' => array_keys(PassportServiceProvider::MCP_SCOPES),
            'bearer_methods_supported' => ['header'],
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
