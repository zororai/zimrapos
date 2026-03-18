<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->hasAccess()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your trial has expired. Please subscribe to continue.'], 402);
            }
            return redirect()->route('pricing')->with('expired', true);
        }

        return $next($request);
    }
}
