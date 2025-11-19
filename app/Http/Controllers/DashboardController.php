<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        // Placeholder summary; replace with real metrics later
        return response()->json([
            'message' => 'Dashboard summary',
            'role' => $user->role,
            'stats' => [
                'photos' => 0,
                'sales' => 0,
                'earnings' => 0,
            ],
        ]);
    }
}
