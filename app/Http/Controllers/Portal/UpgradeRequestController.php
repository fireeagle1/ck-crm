<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class UpgradeRequestController extends Controller
{
    public function show(Request $request): View
    {
        $services = Service::where('company_id', $request->user()->company_id)
            ->where('status', 'Active')
            ->orderBy('service_short')
            ->get();

        return view('portal.upgrade-request', compact('services'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'request_type' => 'required|in:upgrade,downgrade,new_domain,transfer_domain,other',
            'service_id' => 'nullable|exists:services,service_id',
            'details' => 'required|string|max:5000',
        ]);

        $user = $request->user();
        $company = $user->customer;
        $service = $validated['service_id']
            ? Service::find($validated['service_id'])
            : null;

        // Verify service belongs to this company
        if ($service && $service->company_id !== $user->company_id) {
            abort(403);
        }

        $this->notifyAdmins($user, $company, $validated, $service);

        return redirect()->route('portal.upgrade-request.show')
            ->with('success', 'Your request has been submitted. Our team will review it and get back to you shortly.');
    }

    private function notifyAdmins($user, $company, array $data, ?Service $service): void
    {
        $admins = User::where('is_admin', true)->whereNotNull('email')->get();
        $siteName = Setting::get('site_name', 'CK Enterprises UK');

        $requestTypeLabels = [
            'upgrade' => 'Upgrade',
            'downgrade' => 'Downgrade',
            'new_domain' => 'New Domain Registration',
            'transfer_domain' => 'Domain Transfer',
            'other' => 'Other',
        ];

        $requestTypeLabel = $requestTypeLabels[$data['request_type']] ?? $data['request_type'];

        foreach ($admins as $admin) {
            try {
                Mail::send('emails.upgrade-request', [
                    'requestUser' => $user,
                    'company' => $company,
                    'requestType' => $requestTypeLabel,
                    'service' => $service,
                    'details' => $data['details'],
                    'recipientName' => $admin->first_name ?? 'Admin',
                ], function ($message) use ($admin, $requestTypeLabel, $company, $siteName) {
                    $companyName = $company?->company_name ?? 'Unknown';
                    $message->to($admin->email)
                            ->subject("{$requestTypeLabel} Request from {$companyName} — {$siteName}");
                });
            } catch (\Exception $e) {
                Log::error('Failed to send upgrade request email', [
                    'admin' => $admin->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
