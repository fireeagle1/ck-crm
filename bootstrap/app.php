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
