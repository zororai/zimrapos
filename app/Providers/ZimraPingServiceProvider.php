<?php

namespace App\Providers;

use App\Jobs\ZimraPingJob;
use App\Models\ZimraConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class ZimraPingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Skip during console commands (migrations, etc.)
        if ($this->app->runningInConsole()) {
            return;
        }

        $this->startPingJobIfNeeded();
    }

    protected function startPingJobIfNeeded(): void
    {
        try {
            // Use cache to only check once per minute
            $cacheKey = 'zimra_ping_job_check';
            if (Cache::has($cacheKey)) {
                return;
            }
            Cache::put($cacheKey, true, 60);

            $zimraConfig = ZimraConfig::getActive();

            if (!$zimraConfig || !$zimraConfig->device_id) {
                return;
            }

            // Check if a ping job is already in the queue
            $hasPendingJob = DB::table('jobs')
                ->where('payload', 'like', '%ZimraPingJob%')
                ->exists();

            if (!$hasPendingJob) {
                ZimraPingJob::dispatch()->delay(now()->addSeconds(10));
                Log::info('ZIMRA Ping Job dispatched');
            }
        } catch (\Exception $e) {
            // Silently fail during boot - table might not exist yet
        }
    }
}
