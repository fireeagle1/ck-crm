<?php

namespace App\Http\Controllers;

use App\Mail\AdminNewLead;
use App\Mail\LeadConfirmation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class LeadSignupController extends Controller
{
    /**
     * Where lead notifications are sent to (and confirmations sent from).
     */
    private const NOTIFY_EMAIL = 'Info@ckenterprises.co.uk';

    /**
     * Show the embeddable enquiry form.
     */
    public function show(): View
    {
        return view('signup.form');
    }

    /**
     * Handle an enquiry submission.
     */
    public function store(Request $request): RedirectResponse
    {
        // Honeypot: real users never fill this. Silently accept to avoid tipping off bots.
        if (filled($request->input('company_url'))) {
            return redirect()->route('signup.show')->with('submitted', true);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organisation' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'organisation_type' => ['required', 'string', 'in:Business,Charity,CIC,Community organisation,Other'],
            'organisation_type_other' => ['nullable', 'string', 'max:255', 'required_if:organisation_type,Other'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['string', 'max:255'],
            'services_other' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'heard_about' => ['nullable', 'string', 'max:255'],
            'preferred_contact' => ['nullable', 'string', 'in:Email,Phone,Either'],
            'privacy' => ['accepted'],
        ], [
            'privacy.accepted' => 'Please confirm we can contact you about this enquiry.',
            'services.required' => 'Please choose at least one thing we can help with.',
            'organisation_type_other.required_if' => 'Please tell us what type of organisation you are.',
        ]);

        // Resolve the organisation type "Other" free-text.
        $organisationType = $validated['organisation_type'] === 'Other'
            ? 'Other: ' . ($validated['organisation_type_other'] ?? '')
            : $validated['organisation_type'];

        // Resolve the services "Other" free-text into the list.
        $services = $validated['services'];
        if (in_array('Other', $services, true) && filled($request->input('services_other'))) {
            $services = array_map(
                fn ($s) => $s === 'Other' ? 'Other: ' . $request->input('services_other') : $s,
                $services
            );
        }

        $lead = [
            'name' => $validated['name'],
            'organisation' => $validated['organisation'],
            'organisation_type' => $organisationType,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'website' => $validated['website'] ?? null,
            'services' => $services,
            'message' => $validated['message'],
            'heard_about' => $validated['heard_about'] ?? null,
            'preferred_contact' => $validated['preferred_contact'] ?? null,
        ];

        // Notify the business.
        try {
            Mail::to(self::NOTIFY_EMAIL)->queue(new AdminNewLead($lead));
        } catch (\Throwable $e) {
            Log::error('LeadSignup: failed to queue admin notification', [
                'error' => $e->getMessage(),
            ]);
        }

        // Confirm to the prospect.
        try {
            Mail::to($lead['email'])->queue(new LeadConfirmation($lead));
        } catch (\Throwable $e) {
            Log::error('LeadSignup: failed to queue prospect confirmation', [
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('signup.show')->with('submitted', true);
    }
}
