<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows the response to be embedded in an iframe ONLY on ckenterprises.co.uk
 * (and its subdomains). Uses CSP frame-ancestors, the modern replacement for
 * X-Frame-Options ALLOW-FROM. Applied only to the public embed routes so the
 * rest of the app keeps its default framing behaviour.
 */
class AllowEmbedding
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $allowed = "frame-ancestors 'self' https://ckenterprises.co.uk https://*.ckenterprises.co.uk";

        // Ensure a legacy X-Frame-Options header doesn't block framing.
        $response->headers->remove('X-Frame-Options');
        $response->headers->set('Content-Security-Policy', $allowed);

        return $response;
    }
}
