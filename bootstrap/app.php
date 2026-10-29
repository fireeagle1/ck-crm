<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureIsAdmin::class,
            'onboarded' => \App\Http\Middleware\EnsureOnboarded::class,
            'embed' => \App\Http\Middleware\AllowEmbedding::class,
            'mcp.scope' => \App\Http\Middleware\CheckMcpScope::class,
        ]);

        // The MCP route group needs rate limiting to run BEFORE authentication
        // (design "Middleware order": EnsureHttps -> throttle:mcp -> auth:api ->
        // mcp.scope) so an unauthenticated flood is rejected cheaply before the
        // more expensive Passport token verification (Req 1.6, 13.2). Laravel's
        // default middleware priority runs Authenticate before ThrottleRequests
        // and reorders route-group middleware to match, so we redefine the
        // priority list with ThrottleRequests moved ahead of the auth
        // middleware. This is the framework default order with that single swap;
        // all other groups (web/session) are unaffected because their relative
        // ordering is preserved.
        $middleware->priority([
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);

        // Public enquiry form is embedded cross-domain (iframe on the marketing
        // site), so the session/CSRF cookie won't be present. Protected instead
        // by throttling + honeypot.
        $middleware->validateCsrfTokens(except: [
            'signup',
        ]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(function (Request $request) {
            return $request->user()?->isAdmin() ? '/admin/dashboard' : '/portal/dashboard';
        });

        // Trust all proxies (Cloudflare)
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
