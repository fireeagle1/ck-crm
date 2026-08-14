<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $request->user()->deviceTokens()->updateOrCreate(
            ['token' => $validated['token']],
            ['platform' => 'ios', 'updated_at' => now()]
        );

        return response()->json(['message' => 'Device token registered.'], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $request->user()->deviceTokens()->where('token', $validated['token'])->delete();

        return response()->json(null, 204);
    }
}
