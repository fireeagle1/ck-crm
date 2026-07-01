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
    public function index(): View
    {
        $users = User::with('customer')
            ->orderByDesc('last_login')
            ->paginate(20);

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

        $validated['name'] = $validated['first_name'] . ' ' . $validated['last_name'];
        $validated['password'] = Hash::make($validated['password']);

        User::create($validated);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created.');
    }

    public function impersonate(User $user)
    {
        $admin = auth()->user();

        session()->put('impersonating_from', $admin->id);
        auth()->login($user);

        return redirect()->route('portal.dashboard')
            ->with('info', 'Now impersonating ' . $user->full_name);
    }

    public function stopImpersonating()
    {
        $adminId = session()->pull('impersonating_from');

        if ($adminId) {
            auth()->loginUsingId($adminId);
        }

        return redirect()->route('admin.dashboard')
            ->with('info', 'Impersonation ended.');
    }
}
