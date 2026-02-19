<?php

namespace App\Jobs;

use App\Models\ZimraConfig;
use App\Services\ZimraDeviceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ZimraPingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function handle(ZimraDeviceService $zimra): void
    {
        $zimraConfig = ZimraConfig::getActive();

        if (!$zimraConfig) {
            Log::warning('ZimraPingJob: No active ZIMRA configuration found. Stopping ping loop.');
            return;
        }

        // Execute ping
        $zimra->ping();

        // Get reporting frequency from config (default 300 seconds = 5 minutes)
        $reportingFrequency = $zimraConfig->reporting_frequency ?? 300;

        // Reschedule self
        self::dispatch()->delay(now()->addSeconds($reportingFrequency));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ZimraPingJob failed', [
            'error' => $exception->getMessage()
        ]);

        // Reschedule even on failure (after 60 seconds)
        self::dispatch()->delay(now()->addSeconds(60));
    }
}
