<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Receipt;

class RepairReceiptCounters extends Command
{
    protected $signature = 'zimra:repair-counters {--dry-run : Show what would be done without making changes}';
    protected $description = 'Repair duplicate receipt counters and recalculate counters per fiscal day';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        } else {
            $this->warn('⚠️  This will modify receipt data. Backup created automatically.');
            if (!$this->confirm('Continue with repair?')) {
                $this->info('Repair cancelled');
                return 0;
            }
            
            // Create backup
            $this->call('zimra:backup');
        }

        $this->info('Starting receipt counter repair...');
        $this->newLine();

        // Step 1: Detect and remove duplicates
        $this->step1RemoveDuplicates($dryRun);
        
        // Step 2: Recalculate all receipt counters
        $this->step2RecalculateCounters($dryRun);
        
        // Step 3: Verify integrity
        $this->step3VerifyIntegrity();
        
        $this->newLine();
        $this->info('✓ Repair completed successfully');
        
        if (!$dryRun) {
            $this->info('You can now run: php artisan migrate');
        }
        
        return 0;
    }

    private function step1RemoveDuplicates($dryRun)
    {
        $this->info('STEP 1: Removing duplicate receipt counters');
        $this->line('Strategy: Keep earliest created receipt (MIN id), delete others');
        $this->newLine();

        // Find duplicates
        $duplicates = DB::select("
            SELECT device_id, fiscal_day_no, receipt_counter, COUNT(*) as total
            FROM receipts
            GROUP BY device_id, fiscal_day_no, receipt_counter
            HAVING COUNT(*) > 1
        ");

        if (empty($duplicates)) {
            $this->info('✓ No duplicates found');
            return;
        }

        $this->warn("Found " . count($duplicates) . " duplicate groups");
        
        $totalDeleted = 0;

        foreach ($duplicates as $dup) {
            $receipts = DB::table('receipts')
                ->where('device_id', $dup->device_id)
                ->where('fiscal_day_no', $dup->fiscal_day_no)
                ->where('receipt_counter', $dup->receipt_counter)
                ->orderBy('id')
                ->get(['id', 'receipt_global_no', 'invoice_no', 'created_at']);

            $keepId = $receipts->first()->id;
            $deleteIds = $receipts->skip(1)->pluck('id')->toArray();

            $this->line("Fiscal Day {$dup->fiscal_day_no}, Counter {$dup->receipt_counter}:");
            $this->line("  Keep: ID {$keepId} (Global #{$receipts->first()->receipt_global_no})");
            
            foreach ($deleteIds as $delId) {
                $receipt = $receipts->firstWhere('id', $delId);
                $this->line("  Delete: ID {$delId} (Global #{$receipt->receipt_global_no})");
                
                if (!$dryRun) {
                    DB::table('receipts')->where('id', $delId)->delete();
                    $totalDeleted++;
                }
            }
        }

        if ($dryRun) {
            $this->info("Would delete: " . count($duplicates) . " duplicate receipts");
        } else {
            $this->info("✓ Deleted {$totalDeleted} duplicate receipts");
        }
        
        $this->newLine();
    }

    private function step2RecalculateCounters($dryRun)
    {
        $this->info('STEP 2: Recalculating receipt counters per fiscal day');
        $this->line('Strategy: Reorder by created_at ASC, assign counter 1, 2, 3...');
        $this->newLine();

        // Get all fiscal days with receipts
        $fiscalDays = DB::table('receipts')
            ->select('device_id', 'fiscal_day_no')
            ->groupBy('device_id', 'fiscal_day_no')
            ->orderBy('fiscal_day_no')
            ->get();

        $totalUpdated = 0;

        foreach ($fiscalDays as $fd) {
            $receipts = DB::table('receipts')
                ->where('device_id', $fd->device_id)
                ->where('fiscal_day_no', $fd->fiscal_day_no)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'receipt_counter', 'invoice_no']);

            $this->line("Fiscal Day {$fd->fiscal_day_no}: {$receipts->count()} receipts");

            $counter = 1;
            foreach ($receipts as $receipt) {
                if ($receipt->receipt_counter != $counter) {
                    $this->line("  ID {$receipt->id}: {$receipt->receipt_counter} → {$counter}");
                    
                    if (!$dryRun) {
                        DB::table('receipts')
                            ->where('id', $receipt->id)
                            ->update(['receipt_counter' => $counter]);
                        $totalUpdated++;
                    }
                }
                $counter++;
            }
        }

        if ($dryRun) {
            $this->info("Would update receipt counters for " . $fiscalDays->count() . " fiscal days");
        } else {
            $this->info("✓ Updated {$totalUpdated} receipt counters");
        }
        
        $this->newLine();
    }

    private function step3VerifyIntegrity()
    {
        $this->info('STEP 3: Verifying data integrity');
        $this->newLine();

        // Check for duplicates
        $duplicates = DB::select("
            SELECT device_id, fiscal_day_no, receipt_counter, COUNT(*) as total
            FROM receipts
            GROUP BY device_id, fiscal_day_no, receipt_counter
            HAVING COUNT(*) > 1
        ");

        if (empty($duplicates)) {
            $this->info('✓ No duplicate counters');
        } else {
            $this->error('✗ Still have ' . count($duplicates) . ' duplicate groups');
        }

        // Check for gaps in counters
        $fiscalDays = DB::table('receipts')
            ->select('device_id', 'fiscal_day_no')
            ->groupBy('device_id', 'fiscal_day_no')
            ->get();

        $hasGaps = false;
        foreach ($fiscalDays as $fd) {
            $maxCounter = DB::table('receipts')
                ->where('device_id', $fd->device_id)
                ->where('fiscal_day_no', $fd->fiscal_day_no)
                ->max('receipt_counter');
            
            $count = DB::table('receipts')
                ->where('device_id', $fd->device_id)
                ->where('fiscal_day_no', $fd->fiscal_day_no)
                ->count();

            if ($maxCounter != $count) {
                $this->error("✗ Fiscal Day {$fd->fiscal_day_no}: Max counter {$maxCounter} != Count {$count}");
                $hasGaps = true;
            }
        }

        if (!$hasGaps) {
            $this->info('✓ No gaps in counter sequences');
        }

        // Check global number uniqueness
        $globalDuplicates = DB::select("
            SELECT receipt_global_no, COUNT(*) as total
            FROM receipts
            GROUP BY receipt_global_no
            HAVING COUNT(*) > 1
        ");

        if (empty($globalDuplicates)) {
            $this->info('✓ All global numbers are unique');
        } else {
            $this->error('✗ Found ' . count($globalDuplicates) . ' duplicate global numbers');
        }
    }
}
