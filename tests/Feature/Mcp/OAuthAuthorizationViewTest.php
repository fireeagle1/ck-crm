<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Laravel\Passport\Http\Responses\SimpleViewResponse;
use Tests\TestCase;

/**
 * Reproduces and guards against the production failure where
 * GET /oauth/authorize 500'd with:
 *
 *   BindingResolutionException: Target
 *   [Laravel\Passport\Contracts\AuthorizationViewResponse] is not instantiable.
 *
 * Passport 12+ only binds AuthorizationViewResponse once an authorization view
 * has been registered via Passport::authorizationView(). We register
 * 'oauth.authorize' in App\Providers\PassportServiceProvider; this test drives
 * the real consent endpoint for a PKCE public client and asserts the branded
 * consent screen renders (200) instead of throwing.
 */
class OAuthAuthorizationViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Passport needs signing keys to validate an authorization request.
        Artisan::call('passport:keys', ['--force' => true]);
    }

    public function test_the_authorization_view_response_binding_resolves(): void
    {
        // The direct symptom: before the fix this throw a
        // BindingResolutionException.
        $response = app(AuthorizationViewResponse::class);

        $this->assertInstanceOf(
            SimpleViewResponse::class,
            $response
        );
    }

    public function test_consent_screen_renders_for_a_pkce_public_client(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        // A public (no-secret) authorization-code client, exactly like the MCP
        // connector client created by `passport:client --public`.
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            name: 'mcp_client',
            redirectUris: ['https://chatgpt.com/connector/oauth/z59Zsl9Jikvy'],
            confidential: false,
            user: null,
        );

        // PKCE S256 challenge (value itself is irrelevant to rendering consent).
        $challenge = rtrim(strtr(base64_encode(hash('sha256', 'verifier', true)), '+/', '-_'), '=');

        $query = http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/z59Zsl9Jikvy',
            'response_type' => 'code',
            'scope' => 'tickets:read',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'state' => 'xyz',
        ]);

        $response = $this->actingAs($user)->get('/oauth/authorize?'.$query);

        // The whole point: a real rendered consent page, not a 500.
        $response->assertOk();
        $response->assertSee('Authorization request');
        $response->assertSee('mcp_client');
        // The requested scope's human description is listed.
        $response->assertSee('Search and read support tickets, replies and history.');
        // Approve/deny both target the Passport endpoints.
        $response->assertSee('oauth/authorize', false);
        $response->assertSee('auth_token', false);
    }
}
