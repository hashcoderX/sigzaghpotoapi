<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        Log::info('Middleware EnsureSubscriptionActive for user ' . $user->id . ' role ' . $user->role . ' trial_ends_at ' . $user->trial_ends_at);
        // Only gate photographer/business roles
        if (!in_array($user->role, ['photographer','business'])) {
            return $next($request);
        }
        $now = Carbon::now();
        $trialActive = $user->trial_ends_at && $now->lt($user->trial_ends_at);
        $subscribed = (bool)$user->subscribed_at;
        Log::info('Trial active: ' . ($trialActive ? 'yes' : 'no') . ' subscribed: ' . ($subscribed ? 'yes' : 'no'));
        if (!$trialActive && !$subscribed) {
            return response()->json([
                'message' => 'Trial expired. Upgrade required.',
                'code' => 'TRIAL_EXPIRED',
            ], 402); // Payment Required
        }
        return $next($request);
    }
}
