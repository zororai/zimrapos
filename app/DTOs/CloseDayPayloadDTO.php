<?php

namespace App\DTOs;

/**
 * ZIMRA Fiscal Device Gateway API v7.2 - CloseDay Payload DTO
 * 
 * This DTO represents the EXACT structure required by ZIMRA v7.2 spec.
 * DO NOT modify field names or structure without verifying against official spec.
 */
class CloseDayPayloadDTO
{
    public int $deviceID;
    public int $fiscalDayNo;
    public array $fiscalCounters;
    public array $fiscalDayDeviceSignature;
    public int $receiptCounter;
    public string $fiscalDayClosed;

    public function __construct(
        int $deviceID,
        int $fiscalDayNo,
        array $fiscalCounters,
        array $fiscalDayDeviceSignature,
        int $receiptCounter,
        string $fiscalDayClosed
    ) {
        $this->deviceID = $deviceID;
        $this->fiscalDayNo = $fiscalDayNo;
        $this->fiscalCounters = $fiscalCounters;
        $this->fiscalDayDeviceSignature = $fiscalDayDeviceSignature;
        $this->receiptCounter = $receiptCounter;
        $this->fiscalDayClosed = $fiscalDayClosed;
    }

    /**
     * Convert to array for JSON serialization
     * Field order matches ZIMRA v7.2 specification
     */
    public function toArray(): array
    {
        return [
            'deviceID' => $this->deviceID,
            'fiscalDayNo' => $this->fiscalDayNo,
            'fiscalCounters' => $this->fiscalCounters,
            'fiscalDayDeviceSignature' => $this->fiscalDayDeviceSignature,
            'receiptCounter' => $this->receiptCounter,
            'fiscalDayClosed' => $this->fiscalDayClosed,
        ];
    }

    /**
     * Get payload WITHOUT signature (for canonical string generation)
     */
    public function toArrayWithoutSignature(): array
    {
        return [
            'deviceID' => $this->deviceID,
            'fiscalDayNo' => $this->fiscalDayNo,
            'fiscalCounters' => $this->fiscalCounters,
            'receiptCounter' => $this->receiptCounter,
            'fiscalDayClosed' => $this->fiscalDayClosed,
        ];
    }
}
