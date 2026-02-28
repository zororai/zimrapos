<?php

namespace App\Services;

use App\Models\FiscalDay;
use App\Models\Receipt;
use Illuminate\Support\Facades\Log;

/**
 * ZIMRA v7.2 CloseDay Validation Service
 * 
 * Validates all requirements before sending CloseDay to ZIMRA FDMS.
 */
class CloseDayValidator
{
    /**
     * Validate CloseDay payload before sending to ZIMRA
     * 
     * @param array $payload The CloseDay payload
     * @param FiscalDay $fiscalDay The fiscal day being closed
     * @param int $deviceId The device ID
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validate(array $payload, FiscalDay $fiscalDay, int $deviceId): array
    {
        $errors = [];

        // 1. Validate payload structure
        $structureErrors = self::validatePayloadStructure($payload);
        if (!empty($structureErrors)) {
            $errors = array_merge($errors, $structureErrors);
        }

        // 2. Validate receiptCounter matches last receipt
        $receiptCounterError = self::validateReceiptCounter($payload, $fiscalDay, $deviceId);
        if ($receiptCounterError) {
            $errors[] = $receiptCounterError;
        }

        // 3. Validate fiscal counters match receipts
        $counterErrors = self::validateFiscalCounters($payload, $fiscalDay, $deviceId);
        if (!empty($counterErrors)) {
            $errors = array_merge($errors, $counterErrors);
        }

        // 4. Validate fiscalDayClosed timestamp
        $timestampError = self::validateFiscalDayClosed($payload, $fiscalDay, $deviceId);
        if ($timestampError) {
            $errors[] = $timestampError;
        }

        // 5. Validate no red/gray errors exist
        $validationErrors = self::validateNoReceiptErrors($fiscalDay, $deviceId);
        if (!empty($validationErrors)) {
            $errors = array_merge($errors, $validationErrors);
        }

        $isValid = empty($errors);

        Log::info('CloseDay v7.2 - Validation Result', [
            'valid' => $isValid,
            'error_count' => count($errors),
            'errors' => $errors,
        ]);

        return [
            'valid' => $isValid,
            'errors' => $errors,
        ];
    }

    /**
     * Validate payload has all required fields with correct types
     */
    private static function validatePayloadStructure(array $payload): array
    {
        $errors = [];
        $requiredFields = [
            'deviceID' => 'integer',
            'fiscalDayNo' => 'integer',
            'fiscalDayCounters' => 'array',
            'fiscalDayDeviceSignature' => 'array',
            'receiptCounter' => 'integer',
            'fiscalDayClosed' => 'string',
        ];

        foreach ($requiredFields as $field => $type) {
            if (!isset($payload[$field])) {
                $errors[] = "Missing required field: {$field}";
                continue;
            }

            $actualType = gettype($payload[$field]);
            if ($actualType !== $type) {
                $errors[] = "Field {$field} must be {$type}, got {$actualType}";
            }
        }

        // Validate signature structure
        if (isset($payload['fiscalDayDeviceSignature'])) {
            $signature = $payload['fiscalDayDeviceSignature'];
            if (!isset($signature['hash']) || !isset($signature['signature'])) {
                $errors[] = "fiscalDayDeviceSignature must contain 'hash' and 'signature' fields";
            }
        }

        return $errors;
    }

    /**
     * Validate receiptCounter matches the last receipt in the fiscal day
     */
    private static function validateReceiptCounter(array $payload, FiscalDay $fiscalDay, int $deviceId): ?string
    {
        $maxReceiptCounter = Receipt::where('device_id', $deviceId)
            ->where('fiscal_day_no', $fiscalDay->fiscal_day_no)
            ->where('is_valid', true)
            ->max('receipt_counter');

        if ($maxReceiptCounter === null) {
            $maxReceiptCounter = 0;
        }

        if ($payload['receiptCounter'] !== $maxReceiptCounter) {
            return "receiptCounter mismatch: payload has {$payload['receiptCounter']}, but max receipt_counter in DB is {$maxReceiptCounter}";
        }

        return null;
    }

    /**
     * Validate fiscal counters match sum of receipts
     */
    private static function validateFiscalCounters(array $payload, FiscalDay $fiscalDay, int $deviceId): array
    {
        $errors = [];

        // Get all valid receipts
        $receipts = Receipt::where('device_id', $deviceId)
            ->where('fiscal_day_no', $fiscalDay->fiscal_day_no)
            ->where('is_valid', true)
            ->get();

        $totalReceiptValue = $receipts->sum(function ($receipt) {
            return (float) $receipt->receipt_total;
        });

        // Sum all sales-related counters (SaleByTax + CreditNoteByTax + DebitNoteByTax)
        $totalSalesByTax = 0;
        foreach ($payload['fiscalDayCounters'] as $counter) {
            if (in_array($counter['fiscalCounterType'], ['SaleByTax', 'CreditNoteByTax', 'DebitNoteByTax'])) {
                $totalSalesByTax += $counter['fiscalCounterValue'];
            }
        }

        $totalSalesByTax = round($totalSalesByTax, 2);
        $totalReceiptValue = round($totalReceiptValue, 2);

        // Allow 1 cent tolerance for rounding
        if (abs($totalSalesByTax - $totalReceiptValue) > 0.01) {
            $errors[] = "Fiscal counter total mismatch: Sales counters = {$totalSalesByTax}, Receipt Total = {$totalReceiptValue}, Difference = " . ($totalSalesByTax - $totalReceiptValue);
        }

        return $errors;
    }

    /**
     * Validate fiscalDayClosed is after last receipt date
     */
    private static function validateFiscalDayClosed(array $payload, FiscalDay $fiscalDay, int $deviceId): ?string
    {
        $lastReceipt = Receipt::where('device_id', $deviceId)
            ->where('fiscal_day_no', $fiscalDay->fiscal_day_no)
            ->where('is_valid', true)
            ->orderBy('receipt_date', 'desc')
            ->first();

        if ($lastReceipt) {
            $fiscalDayClosed = \Carbon\Carbon::parse($payload['fiscalDayClosed']);
            $lastReceiptDate = \Carbon\Carbon::parse($lastReceipt->receipt_date);

            if ($fiscalDayClosed->lt($lastReceiptDate)) {
                return "fiscalDayClosed ({$fiscalDayClosed}) is before last receipt date ({$lastReceiptDate})";
            }
        }

        return null;
    }

    /**
     * Validate no receipts have red or gray errors
     */
    private static function validateNoReceiptErrors(FiscalDay $fiscalDay, int $deviceId): array
    {
        $errors = [];

        $redErrorCount = Receipt::where('device_id', $deviceId)
            ->where('fiscal_day_no', $fiscalDay->fiscal_day_no)
            ->where('has_red_errors', true)
            ->count();

        if ($redErrorCount > 0) {
            $errors[] = "Cannot close fiscal day: {$redErrorCount} receipt(s) have RED validation errors";
        }

        $grayErrorCount = Receipt::where('device_id', $deviceId)
            ->where('fiscal_day_no', $fiscalDay->fiscal_day_no)
            ->where('has_gray_errors', true)
            ->count();

        if ($grayErrorCount > 0) {
            $errors[] = "Cannot close fiscal day: {$grayErrorCount} receipt(s) have GRAY validation errors";
        }

        return $errors;
    }
}
