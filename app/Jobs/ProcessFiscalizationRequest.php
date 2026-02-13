<?php

namespace App\Jobs;

use App\Models\FiscalizationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProcessFiscalizationRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public FiscalizationRequest $fiscalizationRequest
    ) {}

    public function handle(): void
    {
        $this->fiscalizationRequest->markAsProcessing();

        try {
            // Local fiscalization - generate a local fiscal code
            $fiscalCode = 'LOCAL-' . strtoupper(Str::random(16));
            
            $this->fiscalizationRequest->markAsSuccess([
                'fiscal_code' => $fiscalCode,
                'fiscalized_at' => now()->toIso8601String(),
                'message' => 'Fiscalization processed locally',
            ]);
        } catch (\Exception $e) {
            $this->fiscalizationRequest->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->fiscalizationRequest->markAsFailed($exception->getMessage());
    }
}
