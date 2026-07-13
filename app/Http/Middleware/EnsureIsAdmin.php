<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow through if the user is impersonating (verify the original user is actually an admin)
        if (session()->has('impersonating_from')) {
            $adminId = session('impersonating_from');
            $admin = User::find($adminId);

            if ($admin && $admin->isAdmin()) {
                return $next($request);
            }

            // Invalid impersonation session — clear it and deny access
            session()->forget('impersonating_from');
            abort(403, 'Unauthorized');
        }

        if (! $request->user()?->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
