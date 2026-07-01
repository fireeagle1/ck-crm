<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(Request $request): View
    {
        $customer = $request->user()->customer;

        return view('portal.onboarding', compact('customer'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'customer_name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'required|string|max:20',
            'country' => 'nullable|string|max:100',
        ]);

        $customer = $request->user()->customer;
        $customer->update($validated);

        return redirect()->route('portal.dashboard')
            ->with('success', 'Welcome! Your account is now set up.');
    }
}
