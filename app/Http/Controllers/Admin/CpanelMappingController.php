<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\WhmConnectionException;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\WhmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CpanelMappingController extends Controller
{
    public function __construct(private WhmService $whm)
    {
    }

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
        $whmError = null;
        $whmAccounts = $this->fetchWhmAccounts($whmError);

        return view('admin.services.cpanel-mapping', compact('services', 'unmapped', 'whmAccounts', 'whmError'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'mappings' => 'required|array',
            'mappings.*.service_id' => 'required|exists:services,service_id',
            'mappings.*.cpanel_username' => 'nullable|string|max:255',
        ]);

        // Normalise: trim + lowercase, empty becomes null.
        $desired = []; // service_id => ?string (last entry wins)
        $rowOf = [];   // service_id => mapping index (for error keys)
        foreach ($validated['mappings'] as $i => $mapping) {
            $name = strtolower(trim((string) ($mapping['cpanel_username'] ?? '')));
            $desired[$mapping['service_id']] = $name === '' ? null : $name;
            $rowOf[$mapping['service_id']] = $i;
        }

        // Validate everything before changing anything.
        $errors = [];
        $claimed = []; // username => service_id within this batch
        foreach ($desired as $serviceId => $name) {
            if ($name === null) {
                continue;
            }
            $key = "mappings.{$rowOf[$serviceId]}.cpanel_username";

            if (isset($claimed[$name])) {
                $other = Service::find($claimed[$name]);
                $errors[$key] = "The cPanel username '{$name}' is already assigned to service "
                    .($other?->service_short ?? "#{$claimed[$name]}").' in this submission.';
                continue;
            }
            $claimed[$name] = $serviceId;

            $holder = Service::whereRaw('LOWER(cpanel_username) = ?', [$name])
                ->where('service_id', '!=', $serviceId)
                ->when(
                    // A holder that is itself being re-mapped in this batch gives the name up.
                    array_filter(array_keys($desired), fn ($id) => $desired[$id] !== $name),
                    fn ($q, $moving) => $q->where(function ($w) use ($moving) {
                        $w->whereNotIn('service_id', $moving);
                    })
                )
                ->first();

            if ($holder) {
                $errors[$key] = "The cPanel username '{$name}' is already assigned to service "
                    ."'{$holder->service_short}' (#{$holder->service_id}).";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $updated = DB::transaction(function () use ($desired) {
            $count = 0;
            foreach ($desired as $serviceId => $name) {
                $service = Service::find($serviceId);
                if ($service && $service->cpanel_username !== $name) {
                    $service->update(['cpanel_username' => $name]);
                    $count++;
                }
            }

            return $count;
        });

        return back()->with('success', "{$updated} service(s) updated.");
    }

    private function fetchWhmAccounts(?string &$error = null): array
    {
        try {
            return collect($this->whm->listAccounts())->sortBy('user')->values()->toArray();
        } catch (WhmConnectionException $e) {
            $error = 'Could not load accounts from WHM: '.$e->getMessage();
        }

        return [];
    }
}
