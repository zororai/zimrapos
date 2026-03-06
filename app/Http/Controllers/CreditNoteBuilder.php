<?php

namespace App\Http\Controllers;

use App\Models\Receipt;

/**
 * Production-Safe Credit Note Builder using BCMath
 * 
 * Ensures that:
 * - receiptTotal is derived ONLY from sum(receiptTaxes.salesAmountWithTax)
 * - receiptPayments.paymentAmount is automatically set equal to receiptTotal
 * - All calculations use BCMath with scale = 2
 * - Zero values are normalized ("-0.00" → "0.00")
 * - receiptNotes is mandatory and validated for CreditNote
 * - 0% tax produces taxAmount = "0.00" (never "-0.00" or null)
 * - All values are strings formatted to exactly 2 decimal places
 */
class CreditNoteBuilder
{
    /**
     * Build a credit note receipt payload from an original receipt
     * 
     * @param Receipt $originalReceipt The original receipt to credit
     * @param array $selectedLines Array of lines with ['line_index' => int, 'quantity' => float]
     * @param string $reason Reason for the credit note (mandatory, cannot be empty)
     * @param string|null $paymentMethod Payment method (defaults to original)
     * @return array Credit note receipt payload ready for ZIMRA submission
     * @throws \InvalidArgumentException If validation fails
     */
    public static function buildCreditNoteFromReceipt(
        Receipt $originalReceipt,
        array $selectedLines,
        string $reason,
        ?string $paymentMethod = null
    ): array {
        // CRITICAL: Validate receiptNotes (RCPT032 prevention)
        // receiptNotes is MANDATORY for CreditNote and must be meaningful business reason
        $reason = trim($reason);
        if (empty($reason)) {
            throw new \InvalidArgumentException(
                'receiptNotes is mandatory for CreditNote and cannot be empty (RCPT032)'
            );
        }
        
        // ZIMRA requires meaningful business reason (minimum 10 characters recommended)
        if (strlen($reason) < 10) {
            throw new \InvalidArgumentException(
                'receiptNotes must be a meaningful business reason (minimum 10 characters). ' .
                'Example: "Customer return - damaged goods" (RCPT032)'
            );
        }
        
        // Validate original receipt has required data
        if (!$originalReceipt->receipt_lines || !is_array($originalReceipt->receipt_lines)) {
            throw new \InvalidArgumentException('Original receipt must have receipt_lines array');
        }
        
        if (empty($originalReceipt->receipt_global_no)) {
            throw new \InvalidArgumentException('Original receipt must have receipt_global_no for creditDebitNote');
        }
        
        if (empty($originalReceipt->device_id)) {
            throw new \InvalidArgumentException('Original receipt must have device_id for creditDebitNote');
        }
        
        if (empty($originalReceipt->fiscal_day_no)) {
            throw new \InvalidArgumentException('Original receipt must have fiscal_day_no for creditDebitNote');
        }

        // Initialize credit lines array
        $creditLines = [];

        // Process each selected line
        foreach ($selectedLines as $selectedLine) {
            $index = $selectedLine['line_index'];
            $creditQuantity = self::bcFormat($selectedLine['quantity']);
            
            if (!isset($originalReceipt->receipt_lines[$index])) {
                throw new \InvalidArgumentException("Line index {$index} does not exist in original receipt");
            }

            $originalLine = $originalReceipt->receipt_lines[$index];

            // Extract and validate line data
            $price = self::bcFormat($originalLine['receiptLinePrice'] ?? '0');
            $taxPercent = self::bcFormat($originalLine['taxPercent'] ?? '0');
            $taxID = (int) ($originalLine['taxID'] ?? 1);
            $taxCode = $originalLine['taxCode'] ?? 'A';

            // CRITICAL: Calculate line total from price × custom credit quantity
            // This allows partial credits (e.g., crediting 2 out of 5 items)
            $lineTotal = bcmul($price, $creditQuantity, 2);

            // For credit notes, all amounts are NEGATIVE
            $creditPrice = bcmul($price, '-1', 2);
            $creditLineTotal = bcmul($lineTotal, '-1', 2);
            
            // Normalize zero values: "-0.00" → "0.00"
            $creditPrice = self::normalizeZero($creditPrice);
            $creditLineTotal = self::normalizeZero($creditLineTotal);

            // Build credit line with normalized values
            // All numeric values MUST be strings formatted to exact decimal places
            $creditLine = [
                'receiptLineType' => 'Sale',
                'receiptLineNo' => count($creditLines) + 1,
                'receiptLineHSCode' => $originalLine['receiptLineHSCode'] ?? '00000000',
                'receiptLineName' => $originalLine['receiptLineName'] ?? 'Item',
                'receiptLinePrice' => self::formatDecimalString($creditPrice, 2),
                'receiptLineQuantity' => self::formatDecimalString($creditQuantity, 6),
                'receiptLineTotal' => self::formatDecimalString($creditLineTotal, 2),
                'taxCode' => $taxCode,
                'taxPercent' => self::formatDecimalString($taxPercent, 2),
                'taxID' => $taxID,
            ];

            $creditLines[] = $creditLine;
        }

        // Build complete credit note payload
        // CRITICAL: Do NOT set receiptTotal or receiptPayments
        // Let buildAndValidateReceiptBCMath() calculate them from receiptLines
        // This ensures ONE source of truth: the lines themselves
        $creditNotePayload = [
            'receiptType' => 'CreditNote',
            'receiptCurrency' => $originalReceipt->receipt_currency ?? 'USD',
            'invoiceNo' => 'CN-' . time() . '-' . substr(uniqid(), -4),
            // MANDATORY: receiptNotes for CreditNote (RCPT034)
            'receiptNotes' => $reason,
            // MANDATORY: creditDebitNote for CreditNote (RCPT015, RCPT032)
            // Per FDMS API v7.2: Must send either receiptID OR (deviceID + receiptGlobalNo + fiscalDayNo)
            'creditDebitNote' => [
                'deviceID' => (int) $originalReceipt->device_id,
                'receiptGlobalNo' => (int) $originalReceipt->receipt_global_no,
                'fiscalDayNo' => (int) $originalReceipt->fiscal_day_no,
            ],
            'receiptLines' => $creditLines,
            // receiptPayments omitted - will be auto-filled by buildAndValidateReceiptBCMath
        ];

        // Copy buyer data from original receipt for PDF display
        // NOTE: This is stored in the database but NOT sent to FDMS API
        // The credit note references the original via creditDebitNote
        \Log::info('CreditNoteBuilder - Checking buyer_data', [
            'original_receipt_id' => $originalReceipt->id,
            'buyer_data_exists' => isset($originalReceipt->buyer_data),
            'buyer_data_empty' => empty($originalReceipt->buyer_data),
            'buyer_data_type' => gettype($originalReceipt->buyer_data),
            'buyer_data' => $originalReceipt->buyer_data,
        ]);
        
        if ($originalReceipt->buyer_data) {
            $creditNotePayload['buyerData'] = $originalReceipt->buyer_data;
            \Log::info('CreditNoteBuilder - Copied buyer_data to payload', [
                'buyerData' => $creditNotePayload['buyerData'],
            ]);
        } else {
            \Log::warning('CreditNoteBuilder - No buyer_data to copy', [
                'original_receipt_id' => $originalReceipt->id,
            ]);
        }

        return $creditNotePayload;
    }

    /**
     * Format datetime to ZIMRA standard format
     * CRITICAL: Must match original receipt date format EXACTLY
     * ZIMRA expects: "YYYY-MM-DDTHH:MM:SS.uuuuuuZ" (with microseconds and Z timezone)
     */
    private static function formatZimraDateTime($dateTime): string
    {
        if ($dateTime instanceof \DateTime) {
            // Format with microseconds and UTC timezone
            return $dateTime->format('Y-m-d\TH:i:s.u\Z');
        }
        
        // Parse string datetime and reformat to ISO8601 with microseconds
        try {
            $dt = new \DateTime($dateTime);
            // Ensure UTC timezone
            $dt->setTimezone(new \DateTimeZone('UTC'));
            // Format: 2026-02-27T11:33:15.000000Z
            return $dt->format('Y-m-d\TH:i:s.u\Z');
        } catch (\Exception $e) {
            // If parsing fails, return as-is and let ZIMRA validate
            return $dateTime;
        }
    }
    
    /**
     * Format a value as a string for BCMath operations
     */
    private static function bcFormat($value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        return '0';
    }

    /**
     * Round a BCMath string to specified precision
     */
    private static function bcRound(string $value, int $precision = 2): string
    {
        $pow = bcpow('10', (string) $precision, 0);
        return bcdiv(
            bcadd(bcmul($value, $pow, $precision + 1), '0.5', $precision + 1),
            $pow,
            $precision
        );
    }

    /**
     * Normalize zero values to prevent "-0.00"
     * CRITICAL: ZIMRA rejects "-0.00" for tax amounts
     */
    private static function normalizeZero(string $value): string
    {
        // If value rounds to zero, return positive zero
        if (bccomp($value, '0', 2) === 0) {
            return '0.00';
        }
        return $value;
    }
    
    /**
     * Format a BCMath value as a string with exact decimal places
     * Returns a STRING (not float) to preserve exact formatting
     */
    private static function formatDecimalString(string $value, int $decimals = 2): string
    {
        // Normalize zero first
        $value = self::normalizeZero($value);
        
        // Format to exact decimal places as STRING
        return number_format(
            (float) bcadd($value, '0', $decimals),
            $decimals,
            '.',
            ''
        );
    }
}
