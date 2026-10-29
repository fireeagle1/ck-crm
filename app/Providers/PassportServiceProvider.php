<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

/**
 * Configures Laravel Passport as the OAuth 2.1 authorization server for the
 * ChatGPT MCP integration.
 *
 * This provider is intentionally scoped to Passport concerns only. The existing
 * 'web' guard and Sanctum token handling are untouched (Req 14.1); Passport is
 * reached exclusively via the new 'api' guard (see config/auth.php) and its own
 * /oauth/* routes.
 */
class PassportServiceProvider extends ServiceProvider
{
    /**
     * The exhaustive set of MCP scopes an access token may ever carry.
     *
     * Least privilege (Req 1.3): read-only access to five domains plus
     * read/write on tickets. No scope outside this set can be granted, so a
     * leaked token can never mutate customers, invoices, projects or services.
     *
     * @var array<string, string>
     */
    public const MCP_SCOPES = [
        'customers:read' => 'Read customer records (company and contact details).',
        'invoices:read'  => 'Read invoices and their status.',
        'projects:read'  => 'Read projects and their delivery status.',
        'services:read'  => 'Read the services a customer has.',
        'tickets:read'   => 'Search and read support tickets, replies and history.',
        'tickets:write'  => 'Create, update and reply to support tickets.',
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap Passport configuration.
     */
    public function boot(): void
    {
        // Limit issuable scopes to exactly the MCP set (Req 1.2, 1.3). Any
        // scope requested outside this set is dropped by Passport, so issued
        // tokens carry only the intersection of requested and allowed scopes.
        Passport::tokensCan(self::MCP_SCOPES);

        // No scope is granted by default — the MCP client must explicitly
        // request each scope it needs, keeping tokens minimal.
        Passport::setDefaultScope([]);

        // Bounded token lifetimes (Req 1.7): short-lived access tokens with a
        // longer-but-bounded refresh window, so no long-lived static credential
        // is required. The connector refreshes rather than holding a permanent
        // token.
        Passport::tokensExpireIn(Carbon::now()->addMinutes(60));
        Passport::refreshTokensExpireIn(Carbon::now()->addDays(14));
        Passport::personalAccessTokensExpireIn(Carbon::now()->addMinutes(60));

        // PKCE (Req 1.2): the MCP connector authenticates as a PKCE public
        // client created with `php artisan passport:client --public` (task 6.3).
        // Passport requires a code_challenge/code_verifier for the authorization
        // code grant whenever the client has no secret, so PKCE is enforced by
        // construction for the MCP client — there is no secret to fall back on.
        // We do NOT enable the implicit or password grants, which would bypass
        // the authorization-code + PKCE flow OpenAI's connector uses.

        $this->registerMcpRateLimiter();
    }

    /**
     * Register the `mcp` named rate limiter used by the MCP route group's
     * `throttle:mcp` middleware (Req 13.2).
     *
     * The limiter is keyed per Passport access token so one connector's traffic
     * cannot exhaust another's budget; it falls back to the authenticated user
     * id and finally the client IP when no token is resolvable (e.g. before the
     * `auth:api` guard has run). The per-minute limit is configurable via
     * config('mcp.rate_limit') (default 60). Returning a `Limit::perMinute()`
     * instance lets Laravel emit the standard `429 Too Many Requests` response
     * with `Retry-After` and `X-RateLimit-*` headers automatically.
     */
    protected function registerMcpRateLimiter(): void
    {
        RateLimiter::for('mcp', function (Request $request) {
            $perMinute = (int) config('mcp.rate_limit', 60);

            $key = $request->user()?->token()?->getKey()
                ?? $request->user()?->getAuthIdentifier()
                ?? $request->ip();

            return Limit::perMinute($perMinute)->by('mcp:'.$key);
        });
    }
}
