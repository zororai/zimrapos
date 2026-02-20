<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Models\ZimraConfig;
use App\Services\ZimraDeviceService;
use Illuminate\Http\Request;
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
            'base_url' => 'required|url',
            'device_model' => 'required|string',
            'device_version' => 'required|string',
        ]);

        // Deactivate existing configs
        ZimraConfig::where('is_active', true)->update(['is_active' => false]);

        $config = ZimraConfig::create([
            'base_url' => $validated['base_url'],
            'device_model' => $validated['device_model'],
            'device_version' => $validated['device_version'],
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'ZIMRA configuration stored successfully',
            'config' => $config,
        ], 201);
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

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
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
            $status = $zimra->getStatus();
            
            // Save status data to config for QR code generation
            $config = ZimraConfig::getActive();
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
            $result = $zimra->openDay($fiscalDayNo);
            
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
            $payload = $request->all();
            $result = $zimra->closeDay(empty($payload) ? null : $payload);
            
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
                $zimraStatus = $zimra->getStatus();
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
    | Submit Receipt (mTLS + Signing)
    |--------------------------------------------------------------------------
    */
    public function submitReceipt(Request $request, ZimraDeviceService $zimra)
    {
        try {
            $data = $request->all();
            $result = $zimra->submitReceipt($data);

            // Log full ZIMRA response for debugging
            \Log::info('ZIMRA submitReceipt Response', $result);

            if (isset($result['error']) && $result['error']) {
                return response()->json($result, 400);
            }

            // Validate receiptID - CRITICAL for verification
            $receiptID = $result['data']['receiptID'] ?? $result['receiptID'] ?? null;
            if (!$receiptID) {
                \Log::error('ZIMRA Receipt not accepted - No receiptID returned', $result);
                return response()->json([
                    'error' => 'Receipt not accepted by ZIMRA. No verification possible.',
                    'zimra_response' => $result,
                ], 400);
            }

            // Save receipt to database
            $config = ZimraConfig::getActive();
            
            // Get fiscalDayNo and receiptGlobalNo from request or ZIMRA response
            $fiscalDayNo = $data['fiscalDayNo'] ?? $result['fiscal_day_no'] ?? 1;
            $receiptGlobalNo = $data['receiptGlobalNo'] ?? $data['receiptCounter'] ?? 1;
            
            // Validate QR requirements
            if (!$config->qr_url) {
                \Log::error('QR generation failed: qr_url not set. Call getConfig first.');
                throw new \Exception("QR generation failed: Missing qr_url. Call getConfig first.");
            }
            
            // Build QR string immediately with submitted values
            $qrString = $config->qr_url .
                "?deviceID=" . $config->device_id .
                "&receiptID=" . $receiptID .
                "&fiscalDayNo=" . $fiscalDayNo .
                "&receiptGlobalNo=" . $receiptGlobalNo;
            
            \Log::info('QR String built', ['qr_string' => $qrString]);
            
            $receipt = Receipt::create([
                'device_id' => $config->device_id,
                'invoice_no' => $data['invoiceNo'] ?? '',
                'receipt_type' => $data['receiptType'] ?? 'FiscalInvoice',
                'receipt_currency' => $data['receiptCurrency'] ?? 'USD',
                'receipt_counter' => $data['receiptCounter'] ?? 1,
                'receipt_global_no' => $receiptGlobalNo,
                'fiscal_day_no' => $fiscalDayNo,
                'receipt_total' => $data['receiptTotal'] ?? 0,
                'tax_amount' => $data['receiptTaxes'][0]['taxAmount'] ?? 0,
                'tax_code' => $data['receiptTaxes'][0]['taxCode'] ?? 'A',
                'tax_percent' => $data['receiptTaxes'][0]['taxPercent'] ?? 15,
                'payment_method' => $data['receiptPayments'][0]['moneyTypeCode'] ?? 'Cash',
                'receipt_lines' => $data['receiptLines'] ?? [],
                'receipt_taxes' => $data['receiptTaxes'] ?? [],
                'receipt_payments' => $data['receiptPayments'] ?? [],
                'receipt_hash' => $result['receiptHash'] ?? null,
                'receipt_signature' => $result['data']['receiptServerSignature'] ?? $result['receiptServerSignature'] ?? null,
                'receipt_qr_code' => $qrString,
                'verification_code' => null, // Never generated locally - comes from portal scan
                'zimra_response' => $result,
                'receipt_date' => now(),
            ]);

            $result['receipt_id'] = $receipt->id;
            $result['qr_string'] = $qrString;

            \Log::info('Receipt saved successfully', ['receipt_id' => $receipt->id, 'zimra_receipt_id' => $receiptID]);

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
    | Get All Receipts
    |--------------------------------------------------------------------------
    */
    public function getReceipts()
    {
        $receipts = Receipt::orderBy('created_at', 'desc')->limit(50)->get();
        return response()->json($receipts);
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
}
