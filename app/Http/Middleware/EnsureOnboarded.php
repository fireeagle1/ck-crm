<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip for admins, API requests, and the onboarding route itself
        if (
            !$user ||
            $user->isAdmin() ||
            $request->routeIs('portal.onboarding.*') ||
            $request->routeIs('logout')
        ) {
            return $next($request);
        }

        $customer = $user->customer;

        // Check if customer has required info filled in
        if (!$customer || $this->isIncomplete($customer)) {
            return redirect()->route('portal.onboarding.show');
        }

        return $next($request);
    }

    private function isIncomplete($customer): bool
    {
        return empty($customer->company_name) ||
               empty($customer->address_line1) ||
               empty($customer->city) ||
               empty($customer->postal_code);
    }
}
