<?php

namespace App\Jobs;

use App\Models\FiscalizationRequest;
use App\Services\PanierApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessFiscalizationRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public FiscalizationRequest $fiscalizationRequest
    ) {}

    public function handle(PanierApiService $panierApi): void
    {
        $this->fiscalizationRequest->markAsProcessing();

        try {
            $response = $panierApi->zimraFiscalize(
                $this->fiscalizationRequest->request_data,
                $this->fiscalizationRequest->document_type
            );

            if ($response->successful()) {
                $this->fiscalizationRequest->markAsSuccess($response->json());
            } else {
                $this->fiscalizationRequest->markAsFailed(
                    $response->json('message') ?? $response->body()
                );
            }
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
