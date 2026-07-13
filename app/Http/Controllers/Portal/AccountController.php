<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load('customer');
        $companyUsers = User::where('company_id', $request->user()->company_id)
            ->where('is_admin', false)
            ->orderBy('first_name')
            ->get();

        return view('portal.account', compact('user', 'companyUsers'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:20',
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Account updated.');
    }

    public function updateCompany(Request $request)
    {
        $validated = $request->validate([
            'phone_number' => 'nullable|string|max:20',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
        ]);

        $customer = $request->user()->customer;
        if ($customer) {
            $customer->update($validated);
        }

        return back()->with('success', 'Company details updated.');
    }

    public function addUser(Request $request)
    {
        // Cap at 10 users per company
        $existingCount = User::where('company_id', $request->user()->company_id)
            ->where('is_admin', false)
            ->count();

        if ($existingCount >= 10) {
            return back()->with('error', 'Maximum of 10 users per company reached. Please contact support to increase this limit.');
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
        ]);

        $password = bin2hex(random_bytes(6)); // 12 char random password

        $newUser = User::create([
            'name' => $validated['first_name'] . ' ' . $validated['last_name'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($password),
            'company_id' => $request->user()->company_id,
            'is_admin' => false,
        ]);

        // Send invite email
        try {
            Mail::send('emails.welcome', [
                'recipientName' => $validated['first_name'],
                'email' => $newUser->email,
                'password' => $password,
            ], function ($message) use ($newUser) {
                $message->to($newUser->email, $newUser->full_name)
                        ->subject('You\'ve been invited to ' . \App\Models\Setting::get('site_name', 'CK Enterprises UK'));
            });
        } catch (\Exception) {
            // Don't fail
        }

        return back()->with('success', "User '{$newUser->full_name}' added and invite sent.");
    }

    public function sendPasswordReset(Request $request, User $user)
    {
        // Ensure the user belongs to the same company
        if ($user->company_id !== $request->user()->company_id) {
            abort(403);
        }

        $token = app('auth.password.broker')->createToken($user);
        $user->sendPasswordResetNotification($token);

        return back()->with('success', "Password reset email sent to {$user->email}.");
    }
}
