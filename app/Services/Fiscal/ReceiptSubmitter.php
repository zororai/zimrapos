<?php

namespace App\Services\Fiscal;

use App\Services\ZimraDeviceService;

/**
 * Handles receipt submission to FDMS
 * Decouples fiscal layer from ZIMRA-specific implementation
 */
class ReceiptSubmitter
{
    public function __construct(
        protected ZimraDeviceService $zimraService,
        protected ReceiptValidator $validator
    ) {}

    /**
     * Submit receipt to FDMS with validation
     * 
     * @param array $receipt Receipt data
     * @param int $deviceId ZIMRA device ID
     * @return array Submission result
     * @throws \Exception if validation fails
     */
    public function submit(array $receipt, int $deviceId): array
    {
        // Validate before submission
        $this->validator->validate($receipt);
        $this->validator->validateTaxCalculation($receipt);

        // Submit to ZIMRA FDMS
        return $this->zimraService->submitReceipt($receipt, $deviceId);
    }
}
