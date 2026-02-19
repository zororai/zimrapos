<?php

namespace App\Http\Controllers;

use App\Services\ZimraDeviceService;
use Illuminate\Http\Request;

class ZimraTestController extends Controller
{
    public function test(ZimraDeviceService $zimra)
    {
        $results = [
            'timestamp' => now()->toISOString(),
            'steps' => [],
        ];

        // Step 1: Check Config
        try {
            $config = \App\Models\ZimraConfig::getActive();
            $results['steps']['1_config'] = [
                'status' => 'success',
                'device_id' => $config?->device_id,
                'qr_url' => $config?->qr_url,
                'has_certificate' => !empty($config?->certificate),
            ];
        } catch (\Exception $e) {
            $results['steps']['1_config'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Step 2: Get Device Config from ZIMRA
        try {
            $deviceConfig = $zimra->getConfig();
            $results['steps']['2_get_config'] = [
                'status' => 'success',
                'qr_url' => $deviceConfig['qrUrl'] ?? null,
                'device_operating_mode' => $deviceConfig['deviceOperatingMode'] ?? null,
                'taxes' => $deviceConfig['taxes'] ?? [],
            ];
        } catch (\Exception $e) {
            $results['steps']['2_get_config'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Step 3: Get Device Status
        try {
            $status = $zimra->getStatus();
            $results['steps']['3_get_status'] = [
                'status' => 'success',
                'device_status' => $status['deviceStatus'] ?? null,
                'fiscal_day_status' => $status['fiscalDayStatus'] ?? null,
                'last_fiscal_day_no' => $status['lastFiscalDayNo'] ?? null,
            ];
        } catch (\Exception $e) {
            $results['steps']['3_get_status'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Step 4: Check Fiscal Day
        try {
            $fiscalDay = $zimra->getCurrentFiscalDay();
            $results['steps']['4_fiscal_day'] = [
                'status' => 'success',
                'is_open' => $fiscalDay !== null,
                'fiscal_day_no' => $fiscalDay?->fiscal_day_no,
                'opened_at' => $fiscalDay?->opened_at,
            ];
        } catch (\Exception $e) {
            $results['steps']['4_fiscal_day'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Step 5: Check Receipts
        try {
            $receipts = \App\Models\Receipt::orderBy('created_at', 'desc')->limit(5)->get();
            $results['steps']['5_receipts'] = [
                'status' => 'success',
                'count' => $receipts->count(),
                'latest' => $receipts->first()?->invoice_no,
            ];
        } catch (\Exception $e) {
            $results['steps']['5_receipts'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Summary
        $allSuccess = collect($results['steps'])->every(fn($step) => $step['status'] === 'success');
        $results['summary'] = [
            'all_tests_passed' => $allSuccess,
            'ready_for_receipts' => $allSuccess && ($results['steps']['4_fiscal_day']['is_open'] ?? false),
        ];

        return response()->json($results, 200, [], JSON_PRETTY_PRINT);
    }
}
