<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class EnsureBillingActive
{
    public function handle(Request $request, Closure $next): Response
    {
        // Trial/Subscription gating disabled; always allow through
        return $next($request);
    }
}
