<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SsoController extends Controller
{
    /**
     * Generate a cPanel SSO session URL and redirect the user.
     */
    public function cpanel(Request $request, Service $service)
    {
        $this->guardAccess($request, $service);

        if (!$service->cpanel_username) {
            return back()->with('error', 'No cPanel username configured for this service.');
        }

        $url = $this->createSession($service->cpanel_username, 'cpanel');

        if (!$url) {
            return back()->with('error', 'Could not generate cPanel session. Please try again or contact support.');
        }

        return redirect($url);
    }

    /**
     * Generate a Webmail SSO session URL and redirect the user.
     */
    public function webmail(Request $request, Service $service)
    {
        $this->guardAccess($request, $service);

        if (!$service->cpanel_username) {
            return back()->with('error', 'No cPanel username configured for this service.');
        }

        $url = $this->createSession($service->cpanel_username, 'webmail');

        if (!$url) {
            return back()->with('error', 'Could not generate Webmail session. Please try again or contact support.');
        }

        return redirect($url);
    }

    /**
     * Create a WHM session URL for a given cPanel user.
     *
     * Uses WHM API: create_user_session
     */
    private function createSession(string $username, string $service = 'cpanel'): ?string
    {
        $host = config('services.whm.host');
        $whmUser = config('services.whm.username');
        $token = config('services.whm.token');

        if (!$host || !$whmUser || !$token) {
            return null;
        }

        $apiUrl = "https://{$host}:2087/json-api/create_user_session";

        try {
            $response = Http::withHeaders([
                'Authorization' => "WHM {$whmUser}:{$token}",
            ])
            ->withOptions([
                'verify' => false, // WHM often uses self-signed certs
                'timeout' => 15,
            ])
            ->get($apiUrl, [
                'api.version' => 1,
                'user' => $username,
                'service' => $service, // 'cpanel' or 'webmail'
            ]);

            $data = $response->json();

            if (($data['metadata']['result'] ?? 0) == 1 && !empty($data['data']['url'])) {
                return $data['data']['url'];
            }

            \Log::warning('WHM SSO failed', [
                'user' => $username,
                'service' => $service,
                'response' => $data,
            ]);

            return null;
        } catch (\Exception $e) {
            \Log::error('WHM SSO error', [
                'user' => $username,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function guardAccess(Request $request, Service $service): void
    {
        if ($service->company_id !== $request->user()->company_id) {
            abort(403);
        }
    }
}
