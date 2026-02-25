<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupZimraData extends Command
{
    protected $signature = 'zimra:backup';
    protected $description = 'Backup ZIMRA fiscal data before closing fiscal day';

    public function handle()
    {
        $this->info('Creating ZIMRA data backup...');

        $timestamp = now()->format('Y-m-d_His');
        $backupData = [
            'timestamp' => $timestamp,
            'receipts' => DB::table('receipts')->get(),
            'fiscal_days' => DB::table('fiscal_days')->get(),
            'zimra_configs' => DB::table('zimra_configs')->get(),
        ];

        $filename = "zimra_backup_{$timestamp}.json";
        Storage::disk('local')->put(
            "backups/{$filename}",
            json_encode($backupData, JSON_PRETTY_PRINT)
        );

        $this->info("✓ Backup created: storage/app/backups/{$filename}");
        
        // Keep only last 30 backups
        $this->cleanOldBackups();
        
        return 0;
    }

    private function cleanOldBackups()
    {
        $files = Storage::disk('local')->files('backups');
        $backupFiles = array_filter($files, fn($f) => str_starts_with(basename($f), 'zimra_backup_'));
        
        if (count($backupFiles) > 30) {
            usort($backupFiles, fn($a, $b) => 
                Storage::disk('local')->lastModified($a) <=> 
                Storage::disk('local')->lastModified($b)
            );
            
            $toDelete = array_slice($backupFiles, 0, count($backupFiles) - 30);
            foreach ($toDelete as $file) {
                Storage::disk('local')->delete($file);
            }
            
            $this->info('✓ Cleaned ' . count($toDelete) . ' old backups');
        }
    }
}
