<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\WhmConnectionException;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\WhmService;
use Illuminate\Http\Request;

class SsoController extends Controller
{
    public function __construct(private WhmService $whm)
    {
    }

    /**
     * Generate a cPanel SSO session URL and redirect the user.
     */
    public function cpanel(Request $request, Service $service)
    {
        return $this->sso($request, $service, 'cpaneld');
    }

    public function webmail(Request $request, Service $service)
    {
        return $this->sso($request, $service, 'webmaild');
    }

    private function sso(Request $request, Service $service, string $whmService)
    {
        $this->guardAccess($request, $service);

        if (!$service->cpanel_username) {
            return back()->with('error', 'No cPanel username configured for this service.');
        }

        try {
            $url = $this->whm->createUserSession($service->cpanel_username, $whmService);
        } catch (WhmConnectionException $e) {
            return back()->with('error', "WHM SSO failed for '{$service->cpanel_username}': {$e->getMessage()}");
        }

        return redirect($url);
    }

    private function guardAccess(Request $request, Service $service): void
    {
        if ($service->company_id !== $request->user()->company_id) {
            abort(403);
        }
    }
}
