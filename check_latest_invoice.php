<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Receipt;

echo "=== Latest Invoice Status ===\n\n";

// Get the latest 3 receipts
$receipts = Receipt::orderBy('id', 'desc')->take(3)->get();

foreach ($receipts as $receipt) {
    echo "Receipt #{$receipt->id} - {$receipt->invoice_no}\n";
    echo "  Created: {$receipt->created_at}\n";
    echo "  Status: {$receipt->status}\n";
    echo "  FDMS Receipt ID: " . ($receipt->fdms_receipt_id ?? 'NULL') . "\n";
    echo "  Receipt Date: {$receipt->receipt_date}\n";
    echo "  QR Code: " . ($receipt->receipt_qr_code ? 'Generated' : 'NULL') . "\n";
    
    if ($receipt->status === 'pending') {
        echo "  ⚠️  WARNING: Receipt not submitted to FDMS\n";
    } elseif ($receipt->status === 'failed') {
        echo "  ❌ Submission failed\n";
        if ($receipt->zimra_response && isset($receipt->zimra_response['error'])) {
            echo "  Error: " . ($receipt->zimra_response['message'] ?? 'Unknown') . "\n";
        }
    } elseif ($receipt->status === 'submitted' || $receipt->status === 'finalized') {
        echo "  ✓ Successfully submitted\n";
    }
    echo "\n";
}

// Check if there's a receipt created after 06:40:03
$lastSubmittedTime = '2026-03-12 06:40:03';
$recentReceipts = Receipt::where('created_at', '>', $lastSubmittedTime)->get();

if ($recentReceipts->count() > 0) {
    echo "Receipts created after last submitted time ({$lastSubmittedTime}):\n";
    foreach ($recentReceipts as $r) {
        echo "  - {$r->invoice_no} (Status: {$r->status}, FDMS ID: " . ($r->fdms_receipt_id ?? 'NULL') . ")\n";
    }
} else {
    echo "No receipts created after {$lastSubmittedTime}\n";
}
