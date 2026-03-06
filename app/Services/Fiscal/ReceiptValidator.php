<?php

namespace App\Services\Fiscal;

/**
 * Validates receipt structure before FDMS submission
 * Prevents common FDMS validation errors
 */
class ReceiptValidator
{
    /**
     * Validate receipt structure and business rules
     * 
     * @param array $receipt Receipt data to validate
     * @throws \Exception if validation fails
     */
    public function validate(array $receipt): void
    {
        // Must have receipt lines
        if (empty($receipt['receiptLines'])) {
            throw new \Exception("Receipt must contain at least one line");
        }

        // Receipt total must be positive
        if (!isset($receipt['receiptTotal']) || $receipt['receiptTotal'] <= 0) {
            throw new \Exception("Invalid receipt total: must be positive");
        }

        // Validate each line
        foreach ($receipt['receiptLines'] as $index => $line) {
            $lineNo = $index + 1;

            if (!isset($line['receiptLineQuantity']) || $line['receiptLineQuantity'] <= 0) {
                throw new \Exception("Line {$lineNo}: Invalid quantity (must be positive)");
            }

            if (!isset($line['receiptLinePrice']) || $line['receiptLinePrice'] < 0) {
                throw new \Exception("Line {$lineNo}: Invalid price (must be non-negative)");
            }

            if (!isset($line['receiptLineTotal']) || $line['receiptLineTotal'] < 0) {
                throw new \Exception("Line {$lineNo}: Invalid line total (must be non-negative)");
            }

            if (!isset($line['receiptLineName']) || empty($line['receiptLineName'])) {
                throw new \Exception("Line {$lineNo}: Product name is required");
            }

            if (!isset($line['taxID'])) {
                throw new \Exception("Line {$lineNo}: Tax ID is required");
            }
        }

        // Validate receipt taxes
        if (empty($receipt['receiptTaxes'])) {
            throw new \Exception("Receipt must contain tax information");
        }

        // Validate receipt payments
        if (empty($receipt['receiptPayments'])) {
            throw new \Exception("Receipt must contain payment information");
        }

        // CRITICAL: Validate payment reconciliation (RCPT020 prevention)
        $paymentsTotal = 0;
        foreach ($receipt['receiptPayments'] as $payment) {
            $paymentsTotal += $payment['paymentAmount'] ?? 0;
        }

        if (abs($paymentsTotal - $receipt['receiptTotal']) > 0.01) {
            throw new \Exception(
                "RCPT020 risk: Payment total ({$paymentsTotal}) does not match receipt total ({$receipt['receiptTotal']})"
            );
        }

        // Validate DebitNote/CreditNote specific fields
        if (in_array($receipt['receiptType'] ?? '', ['DebitNote', 'CreditNote'])) {
            if (!isset($receipt['creditDebitNote'])) {
                throw new \Exception("{$receipt['receiptType']} must contain creditDebitNote reference");
            }

            // FDMS API v7.2 requires: receiptGlobalNo (mandatory), deviceID, fiscalDayNo, receiptID
            if (!isset($receipt['creditDebitNote']['receiptGlobalNo'])) {
                throw new \Exception("{$receipt['receiptType']} must reference original receipt global number (receiptGlobalNo)");
            }

            // deviceID and fiscalDayNo are recommended but not strictly required
            // FDMS can look up the receipt by receiptGlobalNo alone
        }

        // Validate receiptNotes (RCPT032 prevention)
        if (isset($receipt['receiptNotes'])) {
            if (strlen($receipt['receiptNotes']) < 1) {
                throw new \Exception("receiptNotes cannot be empty if provided");
            }

            // Check for non-ASCII characters
            if (!mb_check_encoding($receipt['receiptNotes'], 'ASCII')) {
                throw new \Exception("RCPT032 risk: receiptNotes must contain only ASCII characters");
            }
        }
    }

    /**
     * Validate tax calculation consistency (RCPT015 prevention)
     * 
     * @param array $receipt Receipt data
     * @throws \Exception if tax calculations are inconsistent
     */
    public function validateTaxCalculation(array $receipt): void
    {
        $linesTotal = 0;
        foreach ($receipt['receiptLines'] as $line) {
            $linesTotal += $line['receiptLineTotal'] ?? 0;
        }

        $taxesTotal = 0;
        foreach ($receipt['receiptTaxes'] as $tax) {
            $taxesTotal += $tax['salesAmountWithTax'] ?? 0;
        }

        // Allow 1 cent tolerance for rounding
        if (abs($taxesTotal - $receipt['receiptTotal']) > 0.01) {
            throw new \Exception(
                "RCPT015 risk: Tax total ({$taxesTotal}) does not match receipt total ({$receipt['receiptTotal']})"
            );
        }
    }
}
