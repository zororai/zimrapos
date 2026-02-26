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
    | Download Receipt PDF
    |--------------------------------------------------------------------------
    */
    public function downloadReceiptPdf($id)
    {
        $receipt = Receipt::findOrFail($id);
        $config = ZimraConfig::getActive();
        
        return view('receipts.pdf', [
            'receipt' => $receipt,
            'config' => $config,
        ]);
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
            return response()->json([]);
        }

        $receipts = Receipt::where('device_id', $config->device_id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
            
        return response()->json($receipts);
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
}
