<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ProcessQueueJobs
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        // Process queue jobs after response is sent
        // Use cache lock to prevent multiple concurrent processes
        $lockKey = 'queue_processing_lock';
        
        // Only process every 30 seconds minimum
        if (Cache::has($lockKey)) {
            return;
        }

        // Set lock for 30 seconds
        Cache::put($lockKey, true, 30);

        try {
            // Process up to 3 jobs per request
            Artisan::call('queue:work', [
                '--once' => true,
                '--tries' => 3,
                '--quiet' => true,
            ]);
        } catch (\Exception $e) {
            // Silently fail - don't break the app
        }
    }
}
