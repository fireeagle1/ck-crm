<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class PublicRegistrationController extends Controller
{
    /**
     * Display the public company registration form.
     */
    public function create(): View
    {
        return view('auth.public-register');
    }

    /**
     * Handle the public registration submission.
     *
     * Validates company details, primary user, additional users,
     * honeypot fields (bot trap), and T&C acceptance.
     */
    public function store(Request $request): RedirectResponse
    {
        // Honeypot check — bots will fill these hidden fields
        if ($request->filled('website_url') || $request->filled('fax_number')) {
            // Silently reject — don't reveal the trap
            return redirect()->route('register')
                ->with('success', 'Registration submitted successfully.');
        }

        // Timestamp honeypot — form submitted too quickly (under 3 seconds)
        $formLoadedAt = $request->input('_form_token');
        if ($formLoadedAt && (time() - (int) base64_decode($formLoadedAt)) < 3) {
            return redirect()->route('register')
                ->with('success', 'Registration submitted successfully.');
        }

        $validated = $request->validate([
            // Company details
            'company_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],

            // Primary user
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],

            // Additional users (optional)
            'additional_users' => ['nullable', 'array', 'max:5'],
            'additional_users.*.first_name' => ['required_with:additional_users', 'string', 'max:255'],
            'additional_users.*.last_name' => ['required_with:additional_users', 'string', 'max:255'],
            'additional_users.*.email' => ['required_with:additional_users', 'string', 'email', 'max:255', 'distinct', 'unique:users,email'],

            // Terms acceptance
            'terms_accepted' => ['required', 'accepted'],
        ], [
            'terms_accepted.required' => 'You must accept the terms and conditions to register.',
            'terms_accepted.accepted' => 'You must accept the terms and conditions to register.',
            'additional_users.*.email.unique' => 'The email address :input is already registered.',
            'additional_users.*.email.distinct' => 'Duplicate email addresses are not allowed.',
        ]);

        // Create the company record
        $customer = Customer::create([
            'company_name' => $validated['company_name'],
            'customer_name' => $validated['first_name'] . ' ' . $validated['last_name'],
            'phone_number' => $validated['phone_number'] ?? null,
            'address_line1' => $validated['address_line1'],
            'address_line2' => $validated['address_line2'] ?? null,
            'city' => $validated['city'],
            'state' => $validated['state'] ?? null,
            'postal_code' => $validated['postal_code'],
            'country' => $validated['country'] ?? null,
            'terms_accepted_at' => now(),
        ]);

        // Create primary user
        $primaryUser = User::create([
            'name' => $validated['first_name'] . ' ' . $validated['last_name'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'company_id' => $customer->company_id,
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($primaryUser));

        // Create additional users with random passwords (they'll use password reset)
        if (!empty($validated['additional_users'])) {
            foreach ($validated['additional_users'] as $additionalUser) {
                if (empty($additionalUser['email'])) {
                    continue;
                }

                $user = User::create([
                    'name' => $additionalUser['first_name'] . ' ' . $additionalUser['last_name'],
                    'first_name' => $additionalUser['first_name'],
                    'last_name' => $additionalUser['last_name'],
                    'email' => $additionalUser['email'],
                    'company_id' => $customer->company_id,
                    'password' => Hash::make(str()->random(32)),
                ]);

                // Send password reset so additional users can set their own password
                $user->sendPasswordResetNotification(
                    app('auth.password.broker')->createToken($user)
                );
            }
        }

        Auth::login($primaryUser);

        // Redirect to the shop so they can purchase services
        return redirect()->route('portal.shop.index')
            ->with('success', 'Welcome! Your account has been created. Browse our services below to get started.');
    }
}
