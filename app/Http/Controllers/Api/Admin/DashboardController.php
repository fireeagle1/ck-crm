<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(DashboardService $dashboardService): JsonResponse
    {
        return response()->json($dashboardService->getMetrics());
    }
}
