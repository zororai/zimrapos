<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Models\ZimraConfig;
use App\Services\ZimraDeviceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ZimraController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Store ZIMRA Configuration
    |--------------------------------------------------------------------------
    */
    public function storeConfig(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'company_tin' => 'nullable|string|max:50',
            'base_url' => 'required|url',
            'device_model' => 'required|string',
            'device_version' => 'required|string',
        ]);

        // Deactivate existing configs if this is the first one
        $existingCount = ZimraConfig::count();
        if ($existingCount === 0) {
            $isActive = true;
        } else {
            $isActive = false;
        }

        $config = ZimraConfig::create([
            'company_name' => $validated['company_name'],
            'company_tin' => $validated['company_tin'] ?? null,
            'base_url' => $validated['base_url'],
            'device_model' => $validated['device_model'],
            'device_version' => $validated['device_version'],
            'is_active' => $isActive,
        ]);

        return response()->json([
            'message' => 'ZIMRA configuration stored successfully',
            'config' => $config,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Get All ZIMRA Configurations (for company selector)
    |--------------------------------------------------------------------------
    */
    public function getAllConfigs()
    {
        $configs = ZimraConfig::select('id', 'company_name', 'company_tin', 'device_id', 'is_active', 'base_url', 'device_model', 'device_version')
            ->orderBy('company_name')
            ->get();

        return response()->json($configs);
    }

    /*
    |--------------------------------------------------------------------------
    | Get Active ZIMRA Configuration
    |--------------------------------------------------------------------------
    */
    public function getActiveConfig()
    {
        $config = ZimraConfig::getActive();

        if (!$config) {
            return response()->json([
                'message' => 'No active ZIMRA configuration found',
            ], 404);
        }

        return response()->json($config);
    }

    /*
    |--------------------------------------------------------------------------
    | Get ZIMRA Configuration by ID
    |--------------------------------------------------------------------------
    */
    public function getConfigById(int $id)
    {
        $config = ZimraConfig::find($id);

        if (!$config) {
            return response()->json([
                'message' => 'Configuration not found',
            ], 404);
        }

        return response()->json($config);
    }

    /*
    |--------------------------------------------------------------------------
    | Set Active Configuration
    |--------------------------------------------------------------------------
    */
    public function setActiveConfig(int $id)
    {
        $config = ZimraConfig::findOrFail($id);
        
        // Deactivate all other configs
        ZimraConfig::where('id', '!=', $id)->update(['is_active' => false]);
        
        // Activate this one
        $config->update(['is_active' => true]);
        
        // Write certificates to file system for mTLS (if registered)
        if ($config->certificate && $config->private_key) {
            Storage::put('zimra/device_certificate.pem', $config->certificate);
            Storage::put('zimra/device_private.key', $config->private_key);
            
            Log::info('Switched to company config', [
                'config_id' => $config->id,
                'company_name' => $config->company_name,
                'device_id' => $config->device_id,
            ]);
        }

        return response()->json([
            'message' => 'Configuration activated successfully',
            'config' => $config,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update ZIMRA Configuration
    |--------------------------------------------------------------------------
    */
    public function updateConfig(Request $request, int $id)
    {
        $config = ZimraConfig::findOrFail($id);

        $validated = $request->validate([
            'base_url' => 'sometimes|url',
            'device_model' => 'sometimes|string',
            'device_version' => 'sometimes|string',
            'is_active' => 'sometimes|boolean',
        ]);

        // If setting this config as active, deactivate others
        if (isset($validated['is_active']) && $validated['is_active']) {
            ZimraConfig::where('id', '!=', $id)->update(['is_active' => false]);
        }

        $config->update($validated);

        return response()->json([
            'message' => 'ZIMRA configuration updated successfully',
            'config' => $config,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete ZIMRA Configuration
    |--------------------------------------------------------------------------
    */
    public function deleteConfig(int $id)
    {
        $config = ZimraConfig::findOrFail($id);

        // Delete certificate files from storage if they exist
        Storage::delete([
            'zimra/device_certificate.pem',
            'zimra/device_private.key',
            'zimra/device.csr',
        ]);

        $config->delete();

        return response()->json([
            'message' => 'ZIMRA configuration deleted successfully',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Clear Device Registration (keeps config, removes device data)
    |--------------------------------------------------------------------------
    */
    public function clearDeviceRegistration()
    {
        $config = ZimraConfig::getActive();

        if (!$config) {
            return response()->json([
                'message' => 'No active ZIMRA configuration found',
            ], 404);
        }

        // Delete certificate files from storage
        Storage::delete([
            'zimra/device_certificate.pem',
            'zimra/device_private.key',
            'zimra/device.csr',
        ]);

        // Clear device-related fields from config
        $config->update([
            'device_id' => null,
            'serial_number' => null,
            'activation_key' => null,
            'private_key' => null,
            'certificate' => null,
            'qr_url' => null,
            'taxes' => null,
            'device_operating_mode' => null,
            'certificate_valid_till' => null,
            'fiscal_day_status' => null,
            'last_receipt_global_no' => null,
            'last_fiscal_day_no' => null,
        ]);

        return response()->json([
            'message' => 'Device registration cleared successfully. You can now register a new device.',
            'config' => $config->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Register Device
    |--------------------------------------------------------------------------
    */
    public function register(Request $request, ZimraDeviceService $zimra)
    {
        $validated = $request->validate([
            'device_id' => 'required|integer',
            'serial_number' => 'required|string',
            'activation_key' => 'required|string',
        ]);

        try {
            $result = $zimra->registerDevice(
                $validated['device_id'],
                $validated['serial_number'],
                $validated['activation_key']
            );

            // Check if the result contains an error
            if (isset($result['error']) && $result['error']) {
                return response()->json($result, 400);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Upload Existing Certificates
    |--------------------------------------------------------------------------
    */
    public function uploadCertificates(Request $request, ZimraDeviceService $zimra)
    {
        $validated = $request->validate([
            'device_id' => 'required|integer',
            'serial_number' => 'required|string',
            'certificate' => 'required|string',
            'private_key' => 'required|string',
        ]);

        try {
            $result = $zimra->uploadCertificates(
                $validated['device_id'],
                $validated['serial_number'],
                $validated['certificate'],
                $validated['private_key']
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get Device Config (mTLS)
    |--------------------------------------------------------------------------
    */
    public function config(ZimraDeviceService $zimra)
    {
        try {
            return response()->json($zimra->getConfig());
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get Device Status (mTLS)
    |--------------------------------------------------------------------------
    */
    public function status(ZimraDeviceService $zimra)
    {
        try {
            // Get device_id from active config
            $config = ZimraConfig::where('is_active', true)->first();
            if (!$config) {
                return response()->json(['error' => 'No active device configured'], 400);
            }
            
            $status = $zimra->getStatus($config->device_id);
            
            // Save status data to config for QR code generation
            if ($config && !isset($status['error'])) {
                $config->update([
                    'fiscal_day_status' => $status['fiscalDayStatus'] ?? null,
                    'last_receipt_global_no' => $status['lastReceiptGlobalNo'] ?? null,
                    'last_fiscal_day_no' => $status['lastFiscalDayNo'] ?? null,
                ]);
            }
            
            return response()->json($status);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Open Fiscal Day (mTLS)
    |--------------------------------------------------------------------------
    */
    public function openDay(Request $request, ZimraDeviceService $zimra)
    {
        $fiscalDayNo = $request->input('fiscal_day_no');

        try {
            // Get device_id from active config
            $config = \App\Models\ZimraConfig::where('is_active', true)->first();
            if (!$config) {
                return response()->json(['error' => 'No active device configured'], 400);
            }
            
            $result = $zimra->openDay($fiscalDayNo, $config->device_id);
            
            if (isset($result['error']) && $result['error']) {
                return response()->json($result, 400);
            }
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Close Fiscal Day
    |--------------------------------------------------------------------------
    */
    public function closeDay(Request $request, ZimraDeviceService $zimra)
    {
        try {
            // Get device_id from active config
            $config = \App\Models\ZimraConfig::where('is_active', true)->first();
            if (!$config) {
                return response()->json(['error' => 'No active device configured'], 400);
            }
            
            $payload = $request->all();
            $result = $zimra->closeDay(empty($payload) ? null : $payload, $config->device_id);
            
            if (isset($result['error']) && $result['error']) {
                return response()->json($result, 400);
            }
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Force Close Fiscal Day (Local Only)
    |--------------------------------------------------------------------------
    */
    public function forceCloseDay(ZimraDeviceService $zimra)
    {
        try {
            $result = $zimra->forceCloseDay();
            
            if (isset($result['error']) && $result['error']) {
                return response()->json($result, 400);
            }
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get Current Fiscal Day Status
    |--------------------------------------------------------------------------
    */
    public function fiscalDayStatus(ZimraDeviceService $zimra)
    {
        try {
            // Debug: Get config and all fiscal days
            $config = \App\Models\ZimraConfig::getActive();
            $allOpenDays = \App\Models\FiscalDay::where('status', 'open')->get();
            
            // Also get ZIMRA status directly for debugging
            $zimraStatus = null;
            try {
                $config = \App\Models\ZimraConfig::where('is_active', true)->first();
                $zimraStatus = $config ? $zimra->getStatus($config->device_id) : ['error' => 'No active device'];
            } catch (\Exception $e) {
                $zimraStatus = ['error' => $e->getMessage()];
            }
            
            $fiscalDay = $zimra->getCurrentFiscalDay();

            if (!$fiscalDay) {
                return response()->json([
                    'is_open' => false,
                    'message' => 'No open fiscal day',
                    'debug' => [
                        'config_device_id' => $config?->device_id,
                        'all_open_days' => $allOpenDays->toArray(),
                        'zimra_status' => $zimraStatus
                    ]
                ]);
            }

            return response()->json([
                'is_open' => true,
                'fiscal_day' => $fiscalDay
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'is_open' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Sync Fiscal Day with FDMS Status
    |--------------------------------------------------------------------------
    | Calls FDMS getStatus and updates local database to match
    |--------------------------------------------------------------------------
    */
    public function syncFiscalDay(ZimraDeviceService $zimra)
    {
        try {
            $config = \App\Models\ZimraConfig::getActive();
            if (!$config || !$config->device_id) {
                return response()->json([
                    'error' => true,
                    'message' => 'No device registered'
                ], 400);
            }

            // Get FDMS status
            $config = \App\Models\ZimraConfig::where('is_active', true)->first();
            if (!$config) {
                return response()->json(['error' => 'No active device configured'], 400);
            }
            $fdmsStatus = $zimra->getStatus($config->device_id);
            
            if (isset($fdmsStatus['error'])) {
                return response()->json([
                    'error' => true,
                    'message' => 'Failed to get FDMS status',
                    'fdms_status' => $fdmsStatus
                ], 400);
            }

            $fiscalDayStatus = $fdmsStatus['fiscalDayStatus'] ?? null;
            $lastFiscalDayNo = $fdmsStatus['lastFiscalDayNo'] ?? null;
            $lastReceiptGlobalNo = $fdmsStatus['lastReceiptGlobalNo'] ?? 0;

            // Get current local fiscal day
            $localFiscalDay = \App\Models\FiscalDay::getCurrentOpen($config->device_id);

            // Sync logic based on FDMS status
            if ($fiscalDayStatus === 'FiscalDayOpened') {
                // FDMS says day is open - ensure we have matching local record
                if (!$localFiscalDay || $localFiscalDay->fiscal_day_no !== $lastFiscalDayNo) {
                    // Close any mismatched local open days
                    \App\Models\FiscalDay::where('device_id', $config->device_id)
                        ->where('status', 'open')
                        ->update(['status' => 'closed', 'closed_at' => now()]);
                    
                    // Create or update local fiscal day to match FDMS
                    $localFiscalDay = \App\Models\FiscalDay::updateOrCreate(
                        [
                            'device_id' => $config->device_id,
                            'fiscal_day_no' => $lastFiscalDayNo
                        ],
                        [
                            'status' => 'open',
                            'opened_at' => now(),
                            'receipt_counter' => $lastReceiptGlobalNo
                        ]
                    );
                }
            } else {
                // FDMS says day is NOT open - close any local open days
                if ($localFiscalDay) {
                    $localFiscalDay->update([
                        'status' => 'closed',
                        'closed_at' => now()
                    ]);
                    $localFiscalDay = null;
                }
            }

            // Return synced status
            return response()->json([
                'fdms_status' => $fdmsStatus,
                'fiscal_day' => $localFiscalDay ? [
                    'is_open' => true,
                    'fiscal_day' => $localFiscalDay
                ] : [
                    'is_open' => false,
                    'message' => 'No open fiscal day'
                ],
                'synced' => true
            ]);

        } catch (\Exception $e) {
            \Log::error('Sync fiscal day error', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Submit Receipt (mTLS + Signing)
    |--------------------------------------------------------------------------
    */
    public function submitReceipt(Request $request, ZimraDeviceService $zimra)
    {
        try {
            $data = $request->all();
            
            // Get device_id from active config (temporary until middleware is implemented)
            $config = \App\Models\ZimraConfig::where('is_active', true)->first();
            if (!$config) {
                return response()->json([
                    'error' => 'No active ZIMRA device configuration found'
                ], 400);
            }
            
            $deviceId = $config->device_id;
            
            $result = $zimra->submitReceipt($data, $deviceId);

            // Log full ZIMRA response for debugging
            \Log::info('ZIMRA submitReceipt Response', $result);

            if (isset($result['error']) && $result['error']) {
                return response()->json($result, 400);
            }

            // Service already saved the receipt with correct counters
            // Just return the result from the service
            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('submitReceipt failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Submit Credit Note (Production-Safe with BCMath)
    |--------------------------------------------------------------------------
    */
    public function submitCreditNote(Request $request, ZimraDeviceService $zimra)
    {
        try {
            $validated = $request->validate([
                'original_receipt_id' => 'required|integer',
                'selected_lines' => 'required|array|min:1',
                'selected_lines.*.line_index' => 'required|integer|min:0',
                'selected_lines.*.quantity' => 'required|numeric|min:0.01',
                'reason' => 'required|string|max:500',
                'payment_method' => 'nullable|string',
            ]);

            // Get active config
            $config = \App\Models\ZimraConfig::where('is_active', true)->first();
            if (!$config) {
                return response()->json([
                    'error' => 'No active ZIMRA device configuration found'
                ], 400);
            }

            // Get original receipt
            $originalReceipt = Receipt::where('id', $validated['original_receipt_id'])
                ->where('device_id', $config->device_id)
                ->first();

            if (!$originalReceipt) {
                return response()->json([
                    'error' => 'Original receipt not found or does not belong to this device'
                ], 404);
            }

            // Build credit note using BCMath builder
            $creditNotePayload = CreditNoteBuilder::buildCreditNoteFromReceipt(
                $originalReceipt,
                $validated['selected_lines'],
                $validated['reason'],
                $validated['payment_method'] ?? null
            );

            // Log the payload (receiptTotal and receiptPayments will be calculated by buildAndValidateReceiptBCMath)
            \Log::info('Credit Note Payload Built', [
                'receipt_type' => $creditNotePayload['receiptType'],
                'line_count' => count($creditNotePayload['receiptLines']),
                'invoice_no' => $creditNotePayload['invoiceNo'],
            ]);

            // Submit to ZIMRA (buildAndValidateReceiptBCMath will calculate receiptTotal and receiptPayments)
            $result = $zimra->submitReceipt($creditNotePayload, $config->device_id);

            // Log full ZIMRA response
            \Log::info('ZIMRA Credit Note Response', $result);

            if (isset($result['error']) && $result['error']) {
                return response()->json($result, 400);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('submitCreditNote failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get All Invoices (Panier invoices for debit notes)
    |--------------------------------------------------------------------------
    */
    public function getInvoices()
    {
        $config = ZimraConfig::getActive();
        
        if (!$config || !$config->device_id) {
            return response()->json(['invoices' => []]);
        }

        // Get ZIMRA receipts that are FiscalInvoice type (these are the "invoices")
        // These can be used as the basis for creating debit notes
        $receipts = Receipt::where('device_id', $config->device_id)
            ->where('receipt_type', 'FiscalInvoice')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function($receipt) {
                // Extract products from receipt_lines
                $products = [];
                if ($receipt->receipt_lines && is_array($receipt->receipt_lines)) {
                    foreach ($receipt->receipt_lines as $line) {
                        $products[] = [
                            'id' => $line['receiptLineHSCode'] ?? 'PROD-' . uniqid(),
                            'name' => $line['receiptLineName'] ?? 'Product',
                            'selling_price' => abs($line['receiptLinePrice'] ?? 0),
                            'quantity' => $line['receiptLineQuantity'] ?? 1,
                        ];
                    }
                }
                
                return [
                    'id' => $receipt->id,
                    'invoice_number' => $receipt->invoice_no,
                    'total' => abs($receipt->receipt_total ?? 0),
                    'created_at' => $receipt->created_at,
                    'products' => $products,
                    'zimra_fiscalized' => true,
                    'receipt_global_no' => $receipt->receipt_global_no,
                    'receipt_date' => $receipt->receipt_date,
                ];
            });
            
        return response()->json(['invoices' => $receipts]);
    }

    /*
    |--------------------------------------------------------------------------
    | Download Receipt PDF
    |--------------------------------------------------------------------------
    */
    public function downloadReceiptPdf($id)
    {
        $receipt = Receipt::findOrFail($id);
        
        // Generate PDF using dompdf
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('receipts.pdf', ['receipt' => $receipt]);
        
        // Set paper size and orientation
        $pdf->setPaper('a4', 'portrait');
        
        return $pdf->download('receipt-' . $receipt->invoice_no . '.pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | Get All Receipts (filtered by current device)
    |--------------------------------------------------------------------------
    */
    public function getReceipts()
    {
        $config = ZimraConfig::getActive();
        
        if (!$config || !$config->device_id) {
            return response()->json(['receipts' => []]);
        }

        // Get ZIMRA receipts
        $receipts = Receipt::where('device_id', $config->device_id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
        
        // Also get Panier sales that were fiscalized
        $panierSales = \App\Models\PanierSale::where('zimra_fiscalized', true)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function($sale) use ($config) {
                // Find the corresponding ZIMRA receipt
                $receipt = Receipt::where('device_id', $config->device_id)
                    ->where('invoice_no', 'LIKE', '%' . substr($sale->panier_id, -8))
                    ->first();
                
                if ($receipt) {
                    return $receipt;
                }
                
                // If no receipt found, create a pseudo-receipt object from the sale
                return (object)[
                    'id' => $sale->panier_id,
                    'invoice_no' => 'SALE-' . substr($sale->panier_id, -8),
                    'receipt_type' => 'FiscalInvoice',
                    'receipt_currency' => $sale->currency_id ?? 'USD',
                    'receipt_total' => $sale->total,
                    'created_at' => $sale->created_at,
                    'is_panier_sale' => true,
                    'panier_data' => $sale
                ];
            })
            ->filter();
            
        return response()->json(['receipts' => $receipts]);
    }

    /*
    |--------------------------------------------------------------------------
    | Get Next Invoice Number (for current device)
    |--------------------------------------------------------------------------
    */
    public function getNextInvoiceNo()
    {
        $config = ZimraConfig::getActive();
        
        if (!$config || !$config->device_id) {
            return response()->json(['invoice_no' => 'INV-001']);
        }

        // Get all receipts for this device and find the highest invoice number
        $receipts = Receipt::where('device_id', $config->device_id)
            ->whereNotNull('invoice_no')
            ->pluck('invoice_no');

        if ($receipts->isEmpty()) {
            return response()->json(['invoice_no' => 'INV-001']);
        }

        // Find the highest invoice number
        $highestNumber = 0;
        foreach ($receipts as $invoiceNo) {
            if (preg_match('/INV-(\d+)/', $invoiceNo, $matches)) {
                $number = (int) $matches[1];
                if ($number > $highestNumber) {
                    $highestNumber = $number;
                }
            }
        }

        $nextNumber = $highestNumber + 1;
        return response()->json([
            'invoice_no' => 'INV-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT)
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Submit File (mTLS + text/plain)
    |--------------------------------------------------------------------------
    */
    public function submitFile(Request $request, ZimraDeviceService $zimra)
    {
        try {
            $result = $zimra->submitFile($request->all());

            if (isset($result['error']) && $result['error']) {
                return response()->json($result, 400);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get Tax Configuration (VAT Status + Applicable Taxes)
    |--------------------------------------------------------------------------
    */
    public function getTaxConfig(ZimraDeviceService $zimra)
    {
        try {
            $config = ZimraConfig::getActive();
            
            if (!$config || !$config->device_id) {
                return response()->json([
                    'error' => true,
                    'message' => 'No active device configuration found. Please register a device first.',
                    'isVatRegistered' => false,
                    'applicableTaxes' => [],
                ]);
            }

            // Fetch fresh config from FDMS
            $fdmsConfig = $zimra->getConfig($config->device_id);
            
            $vatNumber = $fdmsConfig['vatNumber'] ?? null;
            $isVatRegistered = $vatNumber && $vatNumber !== 'NOT_REGISTERED';
            $applicableTaxes = $fdmsConfig['applicableTaxes'] ?? [];
            
            // Format taxes for frontend
            $formattedTaxes = [];
            foreach ($applicableTaxes as $tax) {
                $formattedTaxes[] = [
                    'taxID' => $tax['taxID'] ?? null,
                    'taxPercent' => $tax['taxPercent'] ?? 0.0,
                    'taxName' => $tax['taxName'] ?? 'Unknown',
                    'taxCode' => $tax['taxCode'] ?? null,
                    'validFrom' => $tax['validFrom'] ?? $tax['taxValidFrom'] ?? null,
                    'validTill' => $tax['validTill'] ?? $tax['taxValidTill'] ?? null,
                ];
            }

            return response()->json([
                'isVatRegistered' => $isVatRegistered,
                'vatNumber' => $vatNumber,
                'applicableTaxes' => $formattedTaxes,
                'deviceOperatingMode' => $fdmsConfig['deviceOperatingMode'] ?? 'Unknown',
                'message' => $isVatRegistered 
                    ? 'Device is VAT registered. All tax rates available.' 
                    : 'Device is NOT VAT registered. Only 0% tax allowed.',
            ]);
        } catch (\Exception $e) {
            Log::error('getTaxConfig failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => true,
                'message' => 'Failed to fetch tax configuration: ' . $e->getMessage(),
                'isVatRegistered' => false,
                'applicableTaxes' => [],
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get All Taxes
    |--------------------------------------------------------------------------
    */
    public function getTaxes()
    {
        $taxes = \App\Models\PanierTax::orderBy('zimra_tax_id', 'asc')->get();
        return response()->json(['taxes' => $taxes]);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Tax
    |--------------------------------------------------------------------------
    */
    public function createTax(Request $request)
    {
        try {
            $validated = $request->validate([
                'zimra_tax_id' => 'required|integer|unique:panier_taxes,zimra_tax_id',
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:1',
                'percentage' => 'required|numeric|min:0|max:100',
            ]);

            $tax = \App\Models\PanierTax::create([
                'panier_id' => \Illuminate\Support\Str::ulid()->toString(),
                'zimra_tax_id' => $validated['zimra_tax_id'],
                'name' => $validated['name'],
                'code' => $validated['code'],
                'percentage' => $validated['percentage'],
                'panier_data' => $validated,
            ]);

            return response()->json([
                'success' => true,
                'tax' => $tax,
                'message' => 'Tax created successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => true,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to create tax: ' . $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update Tax
    |--------------------------------------------------------------------------
    */
    public function updateTax(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|string',
                'zimra_tax_id' => 'required|integer',
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:1',
                'percentage' => 'required|numeric|min:0|max:100',
            ]);

            $tax = \App\Models\PanierTax::where('panier_id', $validated['id'])->first();
            
            if (!$tax) {
                return response()->json([
                    'error' => true,
                    'message' => 'Tax not found'
                ], 404);
            }

            // Check if zimra_tax_id is being changed to one that already exists
            if ($tax->zimra_tax_id != $validated['zimra_tax_id']) {
                $exists = \App\Models\PanierTax::where('zimra_tax_id', $validated['zimra_tax_id'])
                    ->where('panier_id', '!=', $validated['id'])
                    ->exists();
                
                if ($exists) {
                    return response()->json([
                        'error' => true,
                        'message' => 'A tax with this ZIMRA Tax ID already exists'
                    ], 422);
                }
            }

            $tax->update([
                'zimra_tax_id' => $validated['zimra_tax_id'],
                'name' => $validated['name'],
                'code' => $validated['code'],
                'percentage' => $validated['percentage'],
                'panier_data' => array_merge($tax->panier_data ?? [], $validated),
            ]);

            return response()->json([
                'success' => true,
                'tax' => $tax,
                'message' => 'Tax updated successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => true,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to update tax: ' . $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Tax
    |--------------------------------------------------------------------------
    */
    public function deleteTax(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|string',
            ]);

            $tax = \App\Models\PanierTax::where('panier_id', $validated['id'])->first();
            
            if (!$tax) {
                return response()->json([
                    'error' => true,
                    'message' => 'Tax not found'
                ], 404);
            }

            // Check if tax is being used by any products
            $productsUsingTax = \App\Models\PanierProduct::where('applicable_tax_id', $tax->panier_id)->count();
            
            if ($productsUsingTax > 0) {
                return response()->json([
                    'error' => true,
                    'message' => "Cannot delete tax. It is currently being used by {$productsUsingTax} product(s)."
                ], 422);
            }

            $tax->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tax deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to delete tax: ' . $e->getMessage()
            ], 500);
        }
    }
}
