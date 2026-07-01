<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class CpanelMappingController extends Controller
{
    public function index(): View
    {
        // Get all services with cpanel usernames
        $services = Service::with('customer')
            ->whereNotNull('cpanel_username')
            ->where('cpanel_username', '!=', '')
            ->orderBy('cpanel_username')
            ->get();

        // Get services WITHOUT cpanel usernames (that might need one)
        $unmapped = Service::with('customer')
            ->where('service_type', 'Web Hosting')
            ->where(function ($q) {
                $q->whereNull('cpanel_username')
                  ->orWhere('cpanel_username', '');
            })
            ->where('status', 'Active')
            ->get();

        // Try to get cPanel accounts from WHM
        $whmAccounts = $this->fetchWhmAccounts();

        return view('admin.services.cpanel-mapping', compact('services', 'unmapped', 'whmAccounts'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'mappings' => 'required|array',
            'mappings.*.service_id' => 'required|exists:services,service_id',
            'mappings.*.cpanel_username' => 'nullable|string|max:255',
        ]);

        $updated = 0;
        foreach ($validated['mappings'] as $mapping) {
            $service = Service::find($mapping['service_id']);
            if ($service && $service->cpanel_username !== ($mapping['cpanel_username'] ?? null)) {
                $service->update(['cpanel_username' => $mapping['cpanel_username'] ?: null]);
                $updated++;
            }
        }

        return back()->with('success', "{$updated} service(s) updated.");
    }

    private function fetchWhmAccounts(): array
    {
        $host = config('services.whm.host');
        $whmUser = config('services.whm.username');
        $token = config('services.whm.token');

        if (!$host || !$whmUser || !$token) {
            return [];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "WHM {$whmUser}:{$token}",
            ])->withOptions(['verify' => false, 'timeout' => 15])
              ->get("https://{$host}:2087/json-api/listaccts", [
                  'api.version' => 1,
              ]);

            $data = $response->json();

            if (($data['metadata']['result'] ?? 0) == 1 && !empty($data['data']['acct'])) {
                return collect($data['data']['acct'])->map(fn($a) => [
                    'user' => $a['user'] ?? '',
                    'domain' => $a['domain'] ?? '',
                    'plan' => $a['plan'] ?? '',
                    'suspended' => ($a['suspended'] ?? 0) == 1,
                ])->sortBy('user')->values()->toArray();
            }
        } catch (\Exception) {
            // silently fail
        }

        return [];
    }
}
