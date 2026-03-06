<?php

namespace App\Services\Fiscal;

/**
 * Centralized tax calculation engine for all fiscal documents
 * Ensures consistent tax math across Sales, Invoices, Debit Notes, Credit Notes
 */
class ReceiptTaxCalculator
{
    /**
     * Calculate line totals and tax amounts
     * 
     * @param float $price Unit price
     * @param float $quantity Quantity
     * @param float $taxPercent Tax percentage (e.g., 15 for 15%)
     * @return array ['lineTotal' => float, 'taxAmount' => float, 'salesAmountWithTax' => float]
     */
    public function calculateLine(float $price, float $quantity, float $taxPercent): array
    {
        bcscale(2);

        // Base amount WITHOUT tax
        $lineTotal = bcmul((string)$price, (string)$quantity, 2);

        // Convert tax percent to decimal (15 → 0.15)
        $taxPercentDecimal = bcdiv((string)$taxPercent, '100', 4);

        // Calculate tax amount
        $taxAmount = bcmul($lineTotal, $taxPercentDecimal, 2);

        // Total including tax
        $salesAmount = bcadd($lineTotal, $taxAmount, 2);

        return [
            'lineTotal' => (float)$lineTotal,
            'taxAmount' => (float)$taxAmount,
            'salesAmountWithTax' => (float)$salesAmount
        ];
    }

    /**
     * Accumulate tax amounts into tax groups
     * 
     * @param array $groups Tax groups array (passed by reference)
     * @param int $taxID ZIMRA tax ID
     * @param float $taxPercent Tax percentage
     * @param float $taxAmount Tax amount to add
     * @param float $salesAmount Sales amount with tax to add
     */
    public function accumulateTaxGroup(array &$groups, int $taxID, float $taxPercent, float $taxAmount, float $salesAmount): void
    {
        $key = "{$taxID}_{$taxPercent}";

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'taxID' => $taxID,
                'taxPercent' => $taxPercent,
                'taxAmount' => 0.0,
                'salesAmountWithTax' => 0.0
            ];
        }

        bcscale(2);
        $groups[$key]['taxAmount'] = bcadd((string)$groups[$key]['taxAmount'], (string)$taxAmount, 2);
        $groups[$key]['salesAmountWithTax'] = bcadd((string)$groups[$key]['salesAmountWithTax'], (string)$salesAmount, 2);
    }

    /**
     * Build receiptTaxes array from accumulated tax groups
     * 
     * @param array $taxGroups Accumulated tax groups
     * @return array Array of receiptTaxes
     */
    public function buildReceiptTaxes(array $taxGroups): array
    {
        $receiptTaxes = [];
        
        foreach ($taxGroups as $tax) {
            $receiptTaxes[] = [
                'taxID' => $tax['taxID'],
                'taxPercent' => $tax['taxPercent'],
                'taxAmount' => round($tax['taxAmount'], 2),
                'salesAmountWithTax' => round($tax['salesAmountWithTax'], 2),
            ];
        }
        
        return $receiptTaxes;
    }
}
