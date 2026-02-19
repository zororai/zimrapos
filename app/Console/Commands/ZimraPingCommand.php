<?php

namespace App\Console\Commands;

use App\Services\ZimraDeviceService;
use Illuminate\Console\Command;

class ZimraPingCommand extends Command
{
    protected $signature = 'zimra:ping';
    protected $description = 'Send heartbeat ping to ZIMRA FDMS';

    public function handle(ZimraDeviceService $zimra): int
    {
        $this->info('Sending ZIMRA Ping...');

        $success = $zimra->ping();

        if ($success) {
            $this->info('Ping successful.');
            return Command::SUCCESS;
        }

        $this->error('Ping failed. Check logs for details.');
        return Command::FAILURE;
    }
}
