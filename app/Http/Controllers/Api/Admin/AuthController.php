<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Maximum failed login attempts before account is locked.
     */
    private const MAX_FAILED_ATTEMPTS = 5;

    /**
     * Lock duration in minutes after exceeding max failed attempts.
     */
    private const LOCK_DURATION_MINUTES = 15;

    /**
     * Authenticate an admin user and issue a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Invalid credentials — user not found
        if (! $user) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // Account is locked
        if ($user->is_locked) {
            // If lock_until is set and has expired, unlock the account
            if ($user->lock_until && $user->lock_until->isPast()) {
                $user->is_locked = false;
                $user->lock_until = null;
                $user->failed_attempts = 0;
                $user->save();
            } else {
                return response()->json([
                    'message' => 'Account is locked. Please try again later.',
                ], 403);
            }
        }

        // Invalid credentials — password mismatch
        if (! Hash::check($validated['password'], $user->password)) {
            $this->recordFailedAttempt($user);

            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // User is not an admin
        if (! $user->isAdmin()) {
            return response()->json([
                'message' => 'Insufficient permissions.',
            ], 403);
        }

        // Successful login — reset failed attempts and update last login
        $user->failed_attempts = 0;
        $user->last_login = now();
        $user->save();

        // Create Sanctum personal access token
        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Record a failed login attempt and lock the account if threshold exceeded.
     */
    private function recordFailedAttempt(User $user): void
    {
        $attempts = ($user->failed_attempts ?? 0) + 1;

        $user->failed_attempts = $attempts;
        $user->last_failed_login = now();

        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            $user->is_locked = true;
            $user->lock_until = now()->addMinutes(self::LOCK_DURATION_MINUTES);

            Log::warning('Account locked due to excessive failed login attempts', [
                'user_id' => $user->id,
                'email' => $user->email,
                'failed_attempts' => $attempts,
            ]);
        }

        $user->save();
    }

    /**
     * Revoke the current token and issue a new one.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoke the current token
        $user->currentAccessToken()->delete();

        // Issue a new token
        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'token' => $token,
        ]);
    }

    /**
     * Revoke the current token and remove device tokens, then return 204.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoke the current access token
        $user->currentAccessToken()->delete();

        // Remove all device tokens for this user (APNs cleanup)
        $user->deviceTokens()->delete();

        return response()->json(null, 204);
    }
}
