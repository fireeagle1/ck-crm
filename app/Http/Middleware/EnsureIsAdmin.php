<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow through if the user is impersonating (they're really an admin)
        if (session()->has('impersonating_from')) {
            return $next($request);
        }

        if (! $request->user()?->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
