<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::with('customer');

        // Search by name or email
        if ($q = $request->input('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        // Hide disabled users by default
        if (!$request->input('show_disabled')) {
            $query->where('is_locked', false);
        }

        $users = $query->orderByDesc('last_login')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $customers = Customer::orderBy('company_name')->get();

        return view('admin.users.create', compact('customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'company_id' => 'required|exists:customers,company_id',
            'phone_number' => 'nullable|string|max:20',
            'is_admin' => 'boolean',
        ]);

        $plainPassword = $validated['password'];
        $isAdmin = $request->boolean('is_admin');

        // Remove is_admin from mass-assignment data — set explicitly
        unset($validated['is_admin']);
        $validated['name'] = $validated['first_name'] . ' ' . $validated['last_name'];
        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);
        $user->is_admin = $isAdmin;
        $user->save();

        // Send welcome email
        try {
            \Illuminate\Support\Facades\Mail::send('emails.welcome', [
                'recipientName' => $validated['first_name'],
                'email' => $user->email,
                'password' => $plainPassword,
            ], function ($message) use ($user) {
                $message->to($user->email, $user->full_name)
                        ->subject('Welcome to ' . \App\Models\Setting::get('site_name', 'CK Enterprises UK'));
            });
        } catch (\Exception) {
            // Don't fail if email fails
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User created and welcome email sent.');
    }

    public function edit(User $user): View
    {
        $customers = Customer::orderBy('company_name')->get();

        return view('admin.users.edit', compact('user', 'customers'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'company_id' => 'required|exists:customers,company_id',
            'phone_number' => 'nullable|string|max:20',
            'is_admin' => 'boolean',
        ]);

        $isAdmin = $request->boolean('is_admin');

        // Remove is_admin from mass-assignment data — set explicitly
        unset($validated['is_admin']);
        $validated['name'] = $validated['first_name'] . ' ' . $validated['last_name'];

        $user->update($validated);
        $user->is_admin = $isAdmin;
        $user->save();

        return redirect()->route('admin.users.edit', $user)
            ->with('success', 'User updated successfully.');
    }

    public function impersonate(Request $request, User $user)
    {
        $admin = auth()->user();

        session()->put('impersonating_from', $admin->id);
        auth()->login($user);
        $request->session()->regenerate();

        return redirect()->route('portal.dashboard')
            ->with('info', 'Now impersonating ' . $user->full_name);
    }

    public function stopImpersonating(Request $request)
    {
        $adminId = session()->pull('impersonating_from');

        if ($adminId) {
            auth()->loginUsingId($adminId);
            $request->session()->regenerate();
        }

        return redirect()->route('admin.dashboard')
            ->with('info', 'Impersonation ended.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'new_password' => 'required|min:8',
        ]);

        $user->password = Hash::make($validated['new_password']);
        $user->failed_attempts = 0;
        $user->is_locked = false;
        $user->lock_until = null;
        $user->save();

        return back()->with('success', "Password reset for {$user->full_name}.");
    }

    public function toggleLock(User $user)
    {
        $user->is_locked = !$user->is_locked;
        $user->lock_until = null;
        $user->failed_attempts = 0;
        $user->save();

        $status = $user->is_locked ? 'disabled' : 'enabled';

        return back()->with('success', "Login {$status} for {$user->full_name}.");
    }
}
