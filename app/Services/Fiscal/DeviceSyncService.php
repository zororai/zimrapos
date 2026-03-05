<?php

namespace App\Services\Fiscal;

use App\Models\DeviceState;
use App\Models\Receipt;
use App\Models\ZimraConfig;
use App\Services\ZimraDeviceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeviceSyncService
{
    protected ZimraDeviceService $zimraService;
    protected CounterService $counterService;

    public function __construct(
        ZimraDeviceService $zimraService,
        CounterService $counterService
    ) {
        $this->zimraService = $zimraService;
        $this->counterService = $counterService;
    }

    /**
     * Sync device state with FDMS
     * Call this on application startup or after recovery
     */
    public function syncWithFdms(int $deviceId): array
    {
        Log::info('DeviceSyncService: Starting sync with FDMS', ['device_id' => $deviceId]);

        $fdmsStatus = $this->zimraService->getFiscalDayStatus($deviceId);
        
        if (isset($fdmsStatus['error'])) {
            throw new \Exception('Failed to get FDMS status: ' . ($fdmsStatus['message'] ?? 'Unknown error'));
        }

        $this->counterService->setDeviceId($deviceId);
        
        $fdmsFiscalDayNo = $fdmsStatus['lastFiscalDayNo'] ?? 1;
        $fdmsLastGlobalNo = $fdmsStatus['lastReceiptGlobalNo'] ?? 0;

        // Sync counters
        $this->counterService->syncFromFdms($fdmsFiscalDayNo, $fdmsLastGlobalNo);

        // Update last sync timestamp
        DeviceState::where('device_id', $deviceId)->update([
            'last_sync_at' => now(),
        ]);

        Log::info('DeviceSyncService: Sync completed', [
            'device_id' => $deviceId,
            'fdms_fiscal_day_no' => $fdmsFiscalDayNo,
            'fdms_last_global_no' => $fdmsLastGlobalNo,
        ]);

        return [
            'success' => true,
            'fiscal_day_no' => $fdmsFiscalDayNo,
            'last_global_no' => $fdmsLastGlobalNo,
            'synced_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Recover from chain gaps
     * Finds receipts that are missing from the local ledger but exist in FDMS
     */
    public function recoverChainGaps(int $deviceId): array
    {
        Log::info('DeviceSyncService: Checking for chain gaps', ['device_id' => $deviceId]);

        $deviceState = DeviceState::where('device_id', $deviceId)->first();
        if (!$deviceState) {
            return ['gaps' => [], 'message' => 'No device state found'];
        }

        // Find gaps in receipt_global_no sequence
        $gaps = [];
        $lastGlobalNo = $deviceState->last_receipt_global_no;

        // Query all finalized receipts ordered by global_no
        $receipts = Receipt::where('device_id', $deviceId)
            ->where('status', 'finalized')
            ->orderBy('receipt_global_no', 'asc')
            ->pluck('receipt_global_no')
            ->toArray();

        if (empty($receipts)) {
            return ['gaps' => [], 'message' => 'No receipts found'];
        }

        $expectedNo = $receipts[0];
        foreach ($receipts as $globalNo) {
            while ($expectedNo < $globalNo) {
                $gaps[] = $expectedNo;
                $expectedNo++;
            }
            $expectedNo = $globalNo + 1;
        }

        Log::info('DeviceSyncService: Chain gap analysis complete', [
            'device_id' => $deviceId,
            'gaps_found' => count($gaps),
            'gaps' => $gaps,
        ]);

        return [
            'gaps' => $gaps,
            'message' => count($gaps) > 0 
                ? 'Found ' . count($gaps) . ' gaps in receipt chain' 
                : 'No gaps found',
        ];
    }

    /**
     * Recover pending receipts that were created but not finalized
     * This happens if the system crashes after creating a pending receipt
     */
    public function recoverPendingReceipts(int $deviceId): array
    {
        Log::info('DeviceSyncService: Checking for pending receipts', ['device_id' => $deviceId]);

        $pendingReceipts = Receipt::where('device_id', $deviceId)
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        $recovered = [];
        $failed = [];

        foreach ($pendingReceipts as $receipt) {
            // Check how old the receipt is
            $ageMinutes = $receipt->created_at->diffInMinutes(now());
            
            if ($ageMinutes < 5) {
                // Recent - might still be processing
                continue;
            }

            // Old pending receipt - mark as failed
            $receipt->update(['status' => 'failed']);
            $failed[] = [
                'id' => $receipt->id,
                'invoice_no' => $receipt->invoice_no,
                'created_at' => $receipt->created_at->toIso8601String(),
                'age_minutes' => $ageMinutes,
            ];

            Log::warning('DeviceSyncService: Marked stale pending receipt as failed', [
                'receipt_id' => $receipt->id,
                'invoice_no' => $receipt->invoice_no,
                'age_minutes' => $ageMinutes,
            ]);
        }

        // Also check for submitted but not finalized
        $submittedReceipts = Receipt::where('device_id', $deviceId)
            ->where('status', 'submitted')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($submittedReceipts as $receipt) {
            $ageMinutes = $receipt->created_at->diffInMinutes(now());
            
            if ($ageMinutes < 5) {
                continue;
            }

            // Has FDMS receipt ID - can be finalized
            if ($receipt->fdms_receipt_id) {
                $receipt->update(['status' => 'finalized']);
                $recovered[] = [
                    'id' => $receipt->id,
                    'invoice_no' => $receipt->invoice_no,
                    'fdms_receipt_id' => $receipt->fdms_receipt_id,
                    'action' => 'finalized',
                ];

                Log::info('DeviceSyncService: Finalized stuck submitted receipt', [
                    'receipt_id' => $receipt->id,
                    'fdms_receipt_id' => $receipt->fdms_receipt_id,
                ]);
            }
        }

        return [
            'recovered' => $recovered,
            'failed' => $failed,
            'message' => sprintf(
                'Recovered %d receipts, marked %d as failed',
                count($recovered),
                count($failed)
            ),
        ];
    }

    /**
     * Full device recovery
     * Call this after a crash or system restore
     */
    public function fullRecovery(int $deviceId): array
    {
        Log::info('DeviceSyncService: Starting full recovery', ['device_id' => $deviceId]);

        $results = [
            'sync' => $this->syncWithFdms($deviceId),
            'pending' => $this->recoverPendingReceipts($deviceId),
            'gaps' => $this->recoverChainGaps($deviceId),
        ];

        Log::info('DeviceSyncService: Full recovery completed', [
            'device_id' => $deviceId,
            'results' => $results,
        ]);

        return $results;
    }

    /**
     * Resend a failed receipt
     * Only for receipts that failed during FDMS submission
     */
    public function resendFailedReceipt(int $receiptId): array
    {
        $receipt = Receipt::findOrFail($receiptId);

        if ($receipt->status !== 'failed') {
            throw new \Exception("Receipt #{$receiptId} is not in failed status");
        }

        if ($receipt->fdms_receipt_id) {
            throw new \Exception("Receipt #{$receiptId} already has FDMS receipt ID - cannot resend");
        }

        Log::info('DeviceSyncService: Resending failed receipt', [
            'receipt_id' => $receiptId,
            'invoice_no' => $receipt->invoice_no,
        ]);

        // This would need to rebuild the receipt data and resubmit
        // For now, return guidance
        return [
            'success' => false,
            'message' => 'Manual resend not yet implemented. Create a new receipt instead.',
            'receipt_id' => $receiptId,
        ];
    }
}
