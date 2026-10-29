<?php

namespace Tests\Feature\Mcp;

use App\Providers\PassportServiceProvider;
use Tests\TestCase;

/**
 * Verifies the OAuth 2.0 / MCP discovery metadata documents that let ChatGPT's
 * connector discover the authorization server automatically (RFC 8414 / 9728).
 *
 * These endpoints are public and must resolve without authentication; a 404
 * here is exactly what broke the connector's automatic discovery.
 */
class OAuthDiscoveryTest extends TestCase
{
    public function test_authorization_server_metadata_is_public_and_complete(): void
    {
        $response = $this->getJson('/.well-known/oauth-authorization-server');

        $response->assertOk();

        $base = rtrim(url('/'), '/');

        $response->assertJson([
            'issuer' => $base,
            'authorization_endpoint' => $base . '/oauth/authorize',
            'token_endpoint' => $base . '/oauth/token',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'code_challenge_methods_supported' => ['S256'],
        ]);

        // Advertised scopes must be exactly the locked MCP scope set.
        $response->assertExactJson(array_merge($response->json(), [
            'scopes_supported' => array_keys(PassportServiceProvider::MCP_SCOPES),
        ]));
    }

    public function test_protected_resource_metadata_points_at_the_authorization_server(): void
    {
        $response = $this->getJson('/.well-known/oauth-protected-resource');

        $base = rtrim(url('/'), '/');

        $response->assertOk();
        $response->assertJson([
            'resource' => $base . '/api/mcp',
            'authorization_servers' => [$base],
            'bearer_methods_supported' => ['header'],
            'scopes_supported' => array_keys(PassportServiceProvider::MCP_SCOPES),
        ]);
    }

    public function test_discovery_requires_no_authentication(): void
    {
        // No Authorization header, no session — must still be reachable.
        $this->get('/.well-known/oauth-authorization-server')->assertOk();
        $this->get('/.well-known/oauth-protected-resource')->assertOk();
    }
}
