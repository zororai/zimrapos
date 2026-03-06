<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\PanierProduct;
use App\Models\PanierCustomer;
use App\Models\ZimraConfig;
use App\Services\Fiscal\ReceiptBuilder;
use App\Services\Fiscal\ReceiptTaxCalculator;
use App\Services\Fiscal\ReceiptFactory;
use App\Services\Fiscal\ReceiptSubmitter;
use Illuminate\Support\Facades\Log;

class DebitNoteService
{
    public function __construct(
        protected ReceiptBuilder $receiptBuilder,
        protected ReceiptTaxCalculator $taxCalculator,
        protected ReceiptFactory $receiptFactory,
        protected ReceiptSubmitter $receiptSubmitter
    ) {}

    /**
     * Create and submit a debit note to ZIMRA
     * 
     * @param array $noteData Contains: invoice_id, products, reason, customer_id, etc.
     * @return array Result from ZIMRA submission
     */
    public function createDebitNote(array $noteData): array
    {
        // Validate input
        $this->validateInput($noteData);
        
        // Find and validate original receipt
        $originalReceipt = $this->findOriginalReceipt($noteData['invoice_id']);
        $this->validateOriginalReceipt($originalReceipt, $noteData);
        
        // Build debit note payload with delta calculation
        $receiptData = $this->buildDebitNotePayload($originalReceipt, $noteData);
        
        // Submit to ZIMRA using ReceiptSubmitter (includes validation)
        $zimraConfig = ZimraConfig::getActive();
        $result = $this->receiptSubmitter->submit($receiptData, $zimraConfig->device_id);
        
        return [
            'success' => !isset($result['error']),
            'receipt' => $result['receipt'] ?? null,
            'error' => $result['error'] ?? null,
            'fdms_receipt_id' => $result['fdms_receipt_id'] ?? null,
        ];
    }

    /**
     * Validate input data
     */
    protected function validateInput(array $noteData): void
    {
        if (!isset($noteData['invoice_id']) || empty($noteData['invoice_id'])) {
            throw new \Exception('invoice_id is required for debit note creation');
        }

        if (!isset($noteData['reason']) || empty($noteData['reason'])) {
            throw new \Exception('reason is required for debit note creation');
        }
    }

    /**
     * Validate original receipt
     */
    protected function validateOriginalReceipt(Receipt $originalReceipt, array $noteData): void
    {
        if ($originalReceipt->is_voided) {
            throw new \Exception("Original receipt {$originalReceipt->invoice_no} is voided. Cannot create debit note.");
        }

        // Check for duplicate external reference
        if (isset($noteData['external_reference']) && !empty($noteData['external_reference'])) {
            $existing = Receipt::where('external_reference', $noteData['external_reference'])
                ->where('receipt_type', 'DebitNote')
                ->first();
            
            if ($existing) {
                throw new \Exception("Debit note with external reference {$noteData['external_reference']} already exists (Receipt ID: {$existing->id})");
            }
        }

        // Validate currency and device match
        $zimraConfig = ZimraConfig::getActive();
        if (!$zimraConfig || !$zimraConfig->device_id) {
            throw new \Exception('No active ZIMRA configuration found');
        }

        if ($originalReceipt->device_id !== $zimraConfig->device_id) {
            throw new \Exception("Original receipt device_id does not match current device");
        }
    }

    /**
     * Find the original receipt by invoice_id or ID
     */
    protected function findOriginalReceipt(string $invoiceId): Receipt
    {
        $originalReceipt = Receipt::where('invoice_no', $invoiceId)
            ->orWhere('id', $invoiceId)
            ->first();

        if (!$originalReceipt) {
            throw new \Exception("Original receipt not found: {$invoiceId}");
        }

        if (!$originalReceipt->fdms_receipt_id) {
            throw new \Exception("Original receipt {$invoiceId} is not fiscalized. Cannot create debit note.");
        }

        return $originalReceipt;
    }

    /**
     * Build debit note payload with delta calculation
     * 
     * CRITICAL: DebitNote must contain only the DELTA (adjustment) between
     * the corrected invoice and the original invoice, NOT the full corrected values.
     */
    protected function buildDebitNotePayload(Receipt $originalReceipt, array $noteData): array
    {
        $currencyCode = $originalReceipt->receipt_currency;

        // Step 1: Load original receipt lines for delta calculation
        $originalLines = $this->loadOriginalLines($originalReceipt);
        
        // Step 2: Extract product details and calculate deltas
        $products = $noteData['products'] ?? [];
        $receiptLines = [];
        $taxGroups = [];
        $receiptTotal = 0;

        foreach ($products as $product) {
            $productDetails = $this->extractProductDetails($product);
            
            // Step 3: Calculate delta (correctedQty - originalQty) using stable product_id
            $delta = $this->calculateDelta(
                $productDetails['product_id'],
                $productDetails['name'],
                $productDetails['quantity'],
                $originalLines
            );
            
            // Skip non-positive deltas (DebitNote must be positive)
            if ($delta['deltaQty'] <= 0) {
                Log::warning('DEBIT_NOTE_SKIPPED_LINE', [
                    'product_id' => $productDetails['product_id'],
                    'product' => $productDetails['name'],
                    'corrected_qty' => $productDetails['quantity'],
                    'original_qty' => $delta['originalQty'],
                    'delta_qty' => $delta['deltaQty'],
                    'reason' => 'Delta is not positive'
                ]);
                continue;
            }
            
            // Step 4: Build receipt line using delta quantity
            $line = $this->receiptBuilder->buildReceiptLine(
                $productDetails,
                $delta['deltaQty'],
                count($receiptLines) + 1
            );
            
            $receiptLines[] = $line['receiptLine'];
            
            // Step 5: Accumulate tax groups using ReceiptTaxCalculator
            $this->taxCalculator->accumulateTaxGroup(
                $taxGroups,
                $line['taxGroup']['taxID'],
                $line['taxGroup']['taxPercent'],
                $line['taxAmount'],
                $line['salesAmount']
            );
            
            $receiptTotal = bcadd((string)$receiptTotal, (string)$line['salesAmount'], 2);
        }

        // Validate: DebitNote must have positive delta lines
        if (empty($receiptLines)) {
            throw new \Exception('Invalid DebitNote: no positive delta (new qty must exceed original qty)');
        }

        // Step 6: Build receiptTaxes from tax groups using ReceiptTaxCalculator
        $receiptTaxes = $this->taxCalculator->buildReceiptTaxes($taxGroups);
        
        // Step 7: Build receiptPayments (must equal receiptTotal)
        $receiptPayments = [
            [
                'moneyTypeCode' => 'Cash',
                'paymentAmount' => round($receiptTotal, 2),
            ]
        ];

        // Debug logging
        Log::info('DEBIT_NOTE_DELTA_CALCULATION', [
            'original_receipt' => [
                'id' => $originalReceipt->id,
                'invoice_no' => $originalReceipt->invoice_no,
                'receipt_global_no' => $originalReceipt->receipt_global_no,
            ],
            'original_lines' => $originalLines,
            'corrected_products' => $products,
            'delta_lines' => $receiptLines,
            'receiptTotal' => $receiptTotal,
        ]);

        // Copy buyer data from original receipt (debit note should reference same buyer)
        if (!isset($noteData['buyerData']) && $originalReceipt->buyer_data) {
            $noteData['buyerData'] = $originalReceipt->buyer_data;
        }
        
        // Fallback: if customer_id provided, build buyer data from customer
        if (!isset($noteData['buyerData']) && isset($noteData['customer_id'])) {
            $customer = PanierCustomer::where('panier_id', $noteData['customer_id'])->first();
            if ($customer) {
                $noteData['buyerData'] = $this->receiptFactory->buildBuyerData($customer);
            }
        }

        // Step 8: Build final receipt using ReceiptFactory
        return $this->receiptFactory->buildDebitNoteReceipt(
            $originalReceipt,
            $receiptLines,
            $receiptTaxes,
            (float)$receiptTotal,
            $noteData
        );
    }

    /**
     * Load original receipt lines indexed by product_id (stable identifier)
     * Falls back to product name for legacy receipts
     */
    protected function loadOriginalLines(Receipt $originalReceipt): array
    {
        $originalLines = $originalReceipt->receipt_lines ?? [];
        if (is_string($originalLines)) {
            $originalLines = json_decode($originalLines, true) ?? [];
        }
        
        $indexed = [];
        foreach ($originalLines as $line) {
            // Prefer product_id for stable matching (critical fix)
            $productId = $line['product_id'] ?? null;
            if ($productId) {
                $indexed[$productId] = $line;
            } else {
                // Fallback to name for legacy receipts (will be phased out)
                $lineName = $line['receiptLineName'] ?? '';
                if ($lineName) {
                    $indexed[$lineName] = $line;
                }
            }
        }
        
        return $indexed;
    }

    /**
     * Extract product details from request data
     * Supports both new format (name, price, taxID) and legacy format (id lookup)
     * CRITICAL: Always includes product_id for stable matching
     */
    protected function extractProductDetails(array $product): array
    {
        if (isset($product['price']) && isset($product['name'])) {
            // New format: line item details from original receipt
            return [
                'product_id' => $product['product_id'] ?? $product['id'] ?? null,
                'quantity' => $product['quantity'] ?? 1,
                'price' => abs(floatval($product['price'])),
                'taxPercent' => floatval($product['taxPercent'] ?? 0),
                'taxId' => intval($product['taxID'] ?? 1),
                'taxCode' => $product['taxCode'] ?? null,
                'hsCode' => $product['receiptLineHSCode'] ?? '',
                'name' => $product['name'],
            ];
        } else {
            // Legacy format: lookup product by ID
            $productModel = PanierProduct::where('panier_id', $product['id'])->first();
            
            if (!$productModel) {
                throw new \Exception("Product {$product['id']} not found");
            }

            $tax = $productModel->tax;
            if (!$tax) {
                throw new \Exception("Product {$productModel->name} does not have a tax assigned");
            }

            return [
                'product_id' => $productModel->panier_id,
                'quantity' => $product['quantity'] ?? 1,
                'price' => abs($productModel->selling_price ?? 0),
                'taxPercent' => (float) $tax->percentage,
                'taxId' => $tax->zimra_tax_id,
                'taxCode' => $tax->code ?? null,
                'hsCode' => $productModel->hs_code ?? '',
                'name' => $productModel->name,
            ];
        }
    }

    /**
     * Calculate delta between corrected quantity and original quantity
     * Uses product_id for stable matching (critical fix for renamed products)
     * 
     * @param string|null $productId Product ID (preferred)
     * @param string $productName Product name (fallback)
     * @param float $correctedQty Corrected quantity
     * @param array $originalLines Original receipt lines indexed by product_id or name
     * @return array ['deltaQty' => float, 'originalQty' => float]
     */
    protected function calculateDelta(?string $productId, string $productName, float $correctedQty, array $originalLines): array
    {
        $originalQty = 0;
        
        // Prefer product_id for stable matching
        if ($productId && isset($originalLines[$productId])) {
            $originalQty = floatval($originalLines[$productId]['receiptLineQuantity'] ?? 0);
        } elseif (isset($originalLines[$productName])) {
            // Fallback to name for legacy receipts
            $originalQty = floatval($originalLines[$productName]['receiptLineQuantity'] ?? 0);
        }
        
        $deltaQty = round($correctedQty - $originalQty, 2);
        
        return [
            'deltaQty' => $deltaQty,
            'originalQty' => $originalQty,
        ];
    }

}
