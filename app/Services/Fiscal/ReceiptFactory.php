<?php

namespace App\Services\Fiscal;

use App\Models\Receipt;

/**
 * Factory for building FDMS receipt payloads
 * Extracts payload structure logic from business services
 */
class ReceiptFactory
{
    /**
     * Build a DebitNote receipt payload
     * 
     * @param Receipt $originalReceipt Original fiscalized receipt
     * @param array $receiptLines Calculated receipt lines (with delta quantities)
     * @param array $receiptTaxes Calculated receipt taxes
     * @param float $receiptTotal Total amount
     * @param array $noteData Additional data (invoice_no, reason, customer_id, etc.)
     * @return array Complete DebitNote receipt payload
     */
    public function buildDebitNoteReceipt(
        Receipt $originalReceipt,
        array $receiptLines,
        array $receiptTaxes,
        float $receiptTotal,
        array $noteData
    ): array {
        $receiptPayments = [
            [
                'moneyTypeCode' => 'Cash',
                'paymentAmount' => round($receiptTotal, 2),
            ]
        ];

        // Sanitize receiptNotes: ASCII-only, max 50 chars (RCPT032 prevention)
        $receiptNotes = $this->sanitizeReceiptNotes($noteData['reason'] ?? 'Quantity adjustment');

        // Build creditDebitNote reference (FDMS API v7.2 schema)
        $creditDebitNote = [
            'receiptGlobalNo' => $originalReceipt->receipt_global_no,
        ];
        
        // Add optional fields if available
        if ($originalReceipt->device_id) {
            $creditDebitNote['deviceID'] = $originalReceipt->device_id;
        }
        
        if ($originalReceipt->fiscal_day_no) {
            $creditDebitNote['fiscalDayNo'] = $originalReceipt->fiscal_day_no;
        }

        $receipt = [
            'receiptType' => 'DebitNote',
            'receiptCurrency' => $originalReceipt->receipt_currency,
            'invoiceNo' => $noteData['invoice_no'] ?? 'DN-' . time(),
            'receiptDate' => now()->format('Y-m-d\TH:i:s'),
            'receiptLinesTaxInclusive' => false,
            'receiptLines' => $receiptLines,
            'receiptTaxes' => $receiptTaxes,
            'receiptPayments' => $receiptPayments,
            'receiptTotal' => $receiptTotal,
            'receiptPrintForm' => 'Receipt48',
            'receiptNotes' => $receiptNotes,
            'creditDebitNote' => $creditDebitNote,
            'original_receipt_id' => $originalReceipt->id,
            'external_reference' => $noteData['external_reference'] ?? null,
        ];

        // Add buyer data if provided
        if (isset($noteData['buyerData'])) {
            $receipt['buyerData'] = $noteData['buyerData'];
        }

        return $receipt;
    }

    /**
     * Build a CreditNote receipt payload
     * 
     * @param Receipt $originalReceipt Original fiscalized receipt
     * @param array $receiptLines Calculated receipt lines
     * @param array $receiptTaxes Calculated receipt taxes
     * @param float $receiptTotal Total amount
     * @param array $noteData Additional data
     * @return array Complete CreditNote receipt payload
     */
    public function buildCreditNoteReceipt(
        Receipt $originalReceipt,
        array $receiptLines,
        array $receiptTaxes,
        float $receiptTotal,
        array $noteData
    ): array {
        $receiptPayments = [
            [
                'moneyTypeCode' => 'Cash',
                'paymentAmount' => round($receiptTotal, 2),
            ]
        ];

        // Sanitize receiptNotes: ASCII-only, max 50 chars (RCPT032 prevention)
        $receiptNotes = $this->sanitizeReceiptNotes($noteData['reason'] ?? 'Quantity adjustment');

        // Build creditDebitNote reference (FDMS API v7.2 schema)
        $creditDebitNote = [
            'receiptGlobalNo' => $originalReceipt->receipt_global_no,
        ];
        
        // Add optional fields if available
        if ($originalReceipt->device_id) {
            $creditDebitNote['deviceID'] = $originalReceipt->device_id;
        }
        
        if ($originalReceipt->fiscal_day_no) {
            $creditDebitNote['fiscalDayNo'] = $originalReceipt->fiscal_day_no;
        }

        $receipt = [
            'receiptType' => 'CreditNote',
            'receiptCurrency' => $originalReceipt->receipt_currency,
            'invoiceNo' => $noteData['invoice_no'] ?? 'CN-' . time(),
            'receiptDate' => now()->format('Y-m-d\TH:i:s'),
            'receiptLinesTaxInclusive' => false,
            'receiptLines' => $receiptLines,
            'receiptTaxes' => $receiptTaxes,
            'receiptPayments' => $receiptPayments,
            'receiptTotal' => $receiptTotal,
            'receiptPrintForm' => 'Receipt48',
            'receiptNotes' => $receiptNotes,
            'creditDebitNote' => $creditDebitNote,
            'original_receipt_id' => $originalReceipt->id,
            'external_reference' => $noteData['external_reference'] ?? null,
        ];

        // Add buyer data if provided
        if (isset($noteData['buyerData'])) {
            $receipt['buyerData'] = $noteData['buyerData'];
        }

        return $receipt;
    }

    /**
     * Build buyer data from customer model
     * 
     * @param \App\Models\PanierCustomer $customer Customer model
     * @return array Buyer data structure
     */
    public function buildBuyerData($customer): array
    {
        $buyerData = [
            'buyerRegisterName' => $customer->name,
            'buyerTradeName' => $customer->name,
            'buyerTIN' => $customer->tax_id ?? '',
            'buyerContacts' => [
                'phoneNo' => $customer->phone ?? '',
                'email' => $customer->email ?? '',
            ],
            'buyerAddress' => [
                'street' => $customer->address ?? '',
                'city' => '',
                'country' => 'ZW',
            ],
        ];

        // Add VAT number if available in panier_data
        if (isset($customer->panier_data['vat_number']) && !empty($customer->panier_data['vat_number'])) {
            $buyerData['vatNumber'] = $customer->panier_data['vat_number'];
        }

        return $buyerData;
    }

    /**
     * Sanitize receipt notes to prevent RCPT032 errors
     * FDMS requires: ASCII-only, short, simple text
     * 
     * @param string $notes User-provided notes
     * @return string Sanitized notes
     */
    protected function sanitizeReceiptNotes(string $notes): string
    {
        // Remove non-ASCII characters
        $notes = preg_replace('/[^\x20-\x7E]/', '', $notes);
        
        // Limit to 50 characters (safe limit for FDMS)
        $notes = substr($notes, 0, 50);
        
        // Trim whitespace
        $notes = trim($notes);
        
        // Fallback if empty after sanitization
        if (empty($notes)) {
            $notes = 'Adjustment';
        }
        
        return $notes;
    }
}
