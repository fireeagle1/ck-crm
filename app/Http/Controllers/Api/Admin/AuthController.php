<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
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

        // Invalid credentials — user not found or password mismatch
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // Account is locked
        if ($user->is_locked) {
            // If lock_until is set and has expired, the account is no longer locked
            if ($user->lock_until && $user->lock_until->isPast()) {
                $user->is_locked = false;
                $user->lock_until = null;
                $user->save();
            } else {
                return response()->json([
                    'message' => 'Account is locked.',
                ], 403);
            }
        }

        // User is not an admin
        if (! $user->isAdmin()) {
            return response()->json([
                'message' => 'Insufficient permissions.',
            ], 403);
        }

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
