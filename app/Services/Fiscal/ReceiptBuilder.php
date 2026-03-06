<?php

namespace App\Services\Fiscal;

/**
 * Builds FDMS receipt line structures
 * Delegates tax calculations to ReceiptTaxCalculator
 */
class ReceiptBuilder
{
    public function __construct(
        protected ReceiptTaxCalculator $taxCalculator
    ) {}

    /**
     * Build a receipt line with tax calculations
     * 
     * @param array $productDetails Contains: name, price, quantity, taxPercent, taxId, taxCode, hsCode, product_id
     * @param float $quantity The quantity to use (for debit notes, this is the delta)
     * @param int $lineNo Line number in the receipt
     * @return array ['receiptLine' => array, 'taxGroup' => array, 'lineTotal' => float, 'taxAmount' => float, 'salesAmount' => float]
     */
    public function buildReceiptLine(array $productDetails, float $quantity, int $lineNo): array
    {
        $price = $productDetails['price'];
        $taxPercent = $productDetails['taxPercent'];
        $taxId = $productDetails['taxId'];
        $taxCode = $productDetails['taxCode'] ?? null;
        $hsCode = $productDetails['hsCode'] ?? '';
        $name = $productDetails['name'];
        $productId = $productDetails['product_id'] ?? null;

        // Delegate tax calculation to ReceiptTaxCalculator
        $taxCalc = $this->taxCalculator->calculateLine($price, $quantity, $taxPercent);

        // Fix receiptLineHSCode: use "0000" if empty (FDMS requirement)
        $hsCodeValue = !empty($hsCode) ? $hsCode : '0000';

        $receiptLine = [
            'receiptLineType' => 'Sale',
            'receiptLineNo' => $lineNo,
            'receiptLineHSCode' => $hsCodeValue,
            'receiptLineName' => $name,
            'receiptLinePrice' => (float)$price,
            'receiptLineQuantity' => (float)$quantity,
            'receiptLineTotal' => $taxCalc['lineTotal'],
            'taxPercent' => (float)$taxPercent,
            'taxID' => $taxId,
        ];

        if ($taxCode) {
            $receiptLine['taxCode'] = $taxCode;
        }

        // NOTE: product_id is stored internally but NOT sent to FDMS
        // It's used for delta calculation matching only

        return [
            'receiptLine' => $receiptLine,
            'taxGroup' => [
                'taxID' => $taxId,
                'taxPercent' => (float)$taxPercent,
            ],
            'lineTotal' => $taxCalc['lineTotal'],
            'taxAmount' => $taxCalc['taxAmount'],
            'salesAmount' => $taxCalc['salesAmountWithTax'],
        ];
    }
}
