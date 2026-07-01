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

        $url = $this->createSession($service->cpanel_username, 'cpaneld');

        if (!$url) {
            // Error detail already flashed by createSession
            return back();
        }

        return redirect($url);
    }

    public function webmail(Request $request, Service $service)
    {
        $this->guardAccess($request, $service);

        if (!$service->cpanel_username) {
            return back()->with('error', 'No cPanel username configured for this service.');
        }

        $url = $this->createSession($service->cpanel_username, 'webmaild');

        if (!$url) {
            return back();
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
            session()->flash('error', 'WHM not configured. Set WHM_HOST, WHM_USERNAME, WHM_API_TOKEN in .env');
            return null;
        }

        $apiUrl = "https://{$host}:2087/json-api/create_user_session";

        try {
            $response = Http::withHeaders([
                'Authorization' => "WHM {$whmUser}:{$token}",
            ])
            ->withOptions([
                'verify' => false,
                'timeout' => 15,
            ])
            ->get($apiUrl, [
                'api.version' => 1,
                'user' => $username,
                'service' => $service,
            ]);

            $data = $response->json();

            if (($data['metadata']['result'] ?? 0) == 1 && !empty($data['data']['url'])) {
                return $data['data']['url'];
            }

            $reason = $data['metadata']['reason'] ?? $data['errors'][0] ?? 'Unknown WHM error';
            session()->flash('error', "WHM SSO failed for '{$username}': {$reason}");

            return null;
        } catch (\Exception $e) {
            session()->flash('error', "WHM connection error: {$e->getMessage()}");
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
