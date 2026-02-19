<?php

namespace App\Jobs;

use App\Services\ZimraDeviceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ZimraAutoCloseDayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 300;

    public function handle(ZimraDeviceService $zimra): void
    {
        $result = $zimra->autoCloseDay();

        if (isset($result['skipped']) && $result['skipped']) {
            Log::debug('ZIMRA Auto Close Day skipped', $result);
            return;
        }

        if (isset($result['error']) && $result['error']) {
            Log::error('ZIMRA Auto Close Day failed', $result);
            return;
        }

        Log::info('ZIMRA Auto Close Day completed', $result);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ZimraAutoCloseDayJob failed', [
            'error' => $exception->getMessage()
        ]);
    }
}
