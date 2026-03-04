<?php

namespace App\Services;

/**
 * ZIMRA FDMS Discount Helper
 * 
 * Handles discount calculations according to FDMS API v7.2 specification.
 * 
 * CRITICAL FDMS RULES:
 * 1. Discounts MUST NOT modify product prices
 * 2. Discounts must be separate receipt lines with receiptLineType = "Discount"
 * 3. Discount values must be negative numbers
 * 4. Discount lines must have same taxCode, taxPercent, taxID as the sale
 * 5. receiptTotal must equal sum of ALL receiptLineTotal values
 * 6. receiptTaxes.salesAmountWithTax must equal final amount after discount
 */
class DiscountHelper
{
    /**
     * Build receipt lines with discounts as separate line items
     * 
     * @param array $items Array of items with name, price, quantity, discount, tax info
     * @param array $defaultTax Default tax configuration (taxCode, taxPercent, taxID)
     * @return array ['receiptLines' => [], 'netTotal' => decimal, 'totalDiscount' => decimal]
     */
    public static function buildReceiptLinesWithDiscounts(array $items, array $defaultTax = []): array
    {
        $receiptLines = [];
        $lineNo = 1;
        $netTotal = '0.00';
        $totalDiscount = '0.00';
        
        // Default tax values
        $defaultTaxCode = $defaultTax['taxCode'] ?? null;
        $defaultTaxPercent = $defaultTax['taxPercent'] ?? '0.00';
        $defaultTaxID = $defaultTax['taxID'] ?? 513;
        
        foreach ($items as $item) {
            $name = $item['name'] ?? 'Item';
            $price = self::formatDecimal($item['price'] ?? 0);
            $quantity = self::formatDecimal($item['quantity'] ?? 1, 6);
            $discount = self::formatDecimal($item['discount'] ?? 0);
            
            // Tax info (item-specific or default)
            $taxCode = $item['taxCode'] ?? $defaultTaxCode;
            $taxPercent = self::formatDecimal($item['taxPercent'] ?? $defaultTaxPercent);
            $taxID = $item['taxID'] ?? $defaultTaxID;
            $hsCode = $item['hsCode'] ?? null;
            
            // Calculate line total using BCMath
            $lineTotal = bcmul($price, $quantity, 2);
            
            // Add sale line (ALWAYS at original price)
            $saleLine = [
                'receiptLineNo' => $lineNo++,
                'receiptLineType' => 'Sale',
                'receiptLineName' => $name,
                'receiptLineQuantity' => $quantity,
                'receiptLinePrice' => $price,
                'receiptLineTotal' => $lineTotal,
                'taxCode' => $taxCode,
                'taxPercent' => $taxPercent,
                'taxID' => $taxID,
            ];
            
            if ($hsCode) {
                $saleLine['receiptLineHSCode'] = $hsCode;
            }
            
            $receiptLines[] = $saleLine;
            
            // Add to net total
            $netTotal = bcadd($netTotal, $lineTotal, 2);
            
            // Add discount line if discount > 0
            if (bccomp($discount, '0', 2) > 0) {
                // Discount must be negative
                $discountAmount = bcmul($discount, '-1', 2);
                
                $discountLine = [
                    'receiptLineNo' => $lineNo++,
                    'receiptLineType' => 'Discount',
                    'receiptLineName' => 'Discount - ' . $name,
                    'receiptLineQuantity' => '1.000000',
                    'receiptLinePrice' => $discountAmount,
                    'receiptLineTotal' => $discountAmount,
                    'taxCode' => $taxCode,
                    'taxPercent' => $taxPercent,
                    'taxID' => $taxID,
                ];
                
                $receiptLines[] = $discountLine;
                
                // Subtract discount from net total
                $netTotal = bcadd($netTotal, $discountAmount, 2);
                
                // Track total discount
                $totalDiscount = bcadd($totalDiscount, $discount, 2);
            }
        }
        
        return [
            'receiptLines' => $receiptLines,
            'netTotal' => $netTotal,
            'totalDiscount' => $totalDiscount,
        ];
    }
    
    /**
     * Calculate receipt taxes based on receipt lines
     * 
     * @param array $receiptLines Array of receipt lines
     * @param bool $taxInclusive Whether prices include tax
     * @return array Array of tax summaries
     */
    public static function calculateReceiptTaxes(array $receiptLines, bool $taxInclusive = false): array
    {
        $taxGroups = [];
        
        foreach ($receiptLines as $line) {
            $lineTotal = $line['receiptLineTotal'] ?? '0.00';
            $taxPercent = $line['taxPercent'] ?? '0.00';
            $taxCode = $line['taxCode'] ?? null;
            $taxID = $line['taxID'] ?? 513;
            
            // Create unique key for tax group
            $key = $taxCode . '_' . $taxPercent . '_' . $taxID;
            
            if (!isset($taxGroups[$key])) {
                $taxGroups[$key] = [
                    'taxCode' => $taxCode,
                    'taxPercent' => $taxPercent,
                    'taxID' => $taxID,
                    'taxAmount' => '0.00',
                    'salesAmountWithTax' => '0.00',
                ];
            }
            
            // Add to sales amount
            $taxGroups[$key]['salesAmountWithTax'] = bcadd(
                $taxGroups[$key]['salesAmountWithTax'],
                $lineTotal,
                2
            );
            
            // Calculate tax amount
            if (bccomp($taxPercent, '0', 2) > 0) {
                if ($taxInclusive) {
                    // Tax = lineTotal - (lineTotal / (1 + taxPercent/100))
                    $divisor = bcadd('1', bcdiv($taxPercent, '100', 6), 6);
                    $amountExclTax = bcdiv($lineTotal, $divisor, 2);
                    $taxAmount = bcsub($lineTotal, $amountExclTax, 2);
                } else {
                    // Tax = lineTotal * (taxPercent / 100)
                    $taxAmount = bcmul($lineTotal, bcdiv($taxPercent, '100', 6), 2);
                }
                
                $taxGroups[$key]['taxAmount'] = bcadd(
                    $taxGroups[$key]['taxAmount'],
                    $taxAmount,
                    2
                );
            }
        }
        
        return array_values($taxGroups);
    }
    
    /**
     * Validate receipt totals match FDMS requirements
     * 
     * @param array $receiptLines
     * @param array $receiptTaxes
     * @param string $receiptTotal
     * @return array ['valid' => bool, 'errors' => []]
     */
    public static function validateReceiptTotals(array $receiptLines, array $receiptTaxes, string $receiptTotal): array
    {
        $errors = [];
        
        // 1. Sum all line totals
        $calculatedTotal = '0.00';
        foreach ($receiptLines as $line) {
            $calculatedTotal = bcadd($calculatedTotal, $line['receiptLineTotal'] ?? '0.00', 2);
        }
        
        // 2. Check receiptTotal matches sum of lines
        if (bccomp($calculatedTotal, $receiptTotal, 2) !== 0) {
            $errors[] = "receiptTotal ($receiptTotal) does not match sum of receiptLineTotal ($calculatedTotal)";
        }
        
        // 3. Sum all tax salesAmountWithTax
        $taxSalesTotal = '0.00';
        foreach ($receiptTaxes as $tax) {
            $taxSalesTotal = bcadd($taxSalesTotal, $tax['salesAmountWithTax'] ?? '0.00', 2);
        }
        
        // 4. Check tax sales total matches receipt total
        if (bccomp($taxSalesTotal, $receiptTotal, 2) !== 0) {
            $errors[] = "Sum of receiptTaxes.salesAmountWithTax ($taxSalesTotal) does not match receiptTotal ($receiptTotal)";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'calculatedTotal' => $calculatedTotal,
            'taxSalesTotal' => $taxSalesTotal,
        ];
    }
    
    /**
     * Format decimal value with specified precision
     * 
     * @param mixed $value
     * @param int $decimals
     * @return string
     */
    private static function formatDecimal($value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, '.', '');
    }
}
