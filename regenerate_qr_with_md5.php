<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Receipt;
use App\Models\ZimraConfig;

echo "=== Regenerating QR Codes with MD5(signature) ===\n\n";

// Get all receipts with FDMS receipt IDs
$receipts = Receipt::whereNotNull('fdms_receipt_id')
    ->whereNotNull('receipt_signature')
    ->where('status', 'finalized')
    ->orderBy('id', 'desc')
    ->take(5)
    ->get();

echo "Found {$receipts->count()} receipts to regenerate\n\n";

foreach ($receipts as $receipt) {
    echo "Processing Receipt #{$receipt->id} - {$receipt->invoice_no}\n";
    
    // Get ZIMRA config
    $zimraConfig = ZimraConfig::where('device_id', $receipt->device_id)->first();
    
    if (!$zimraConfig || !$zimraConfig->qr_url) {
        echo "  ❌ No ZIMRA config or QR URL for device {$receipt->device_id}\n\n";
        continue;
    }
    
    // Get device signature from receipt_signature field
    $deviceSignature = $receipt->receipt_signature['signature'] ?? null;
    
    if (!$deviceSignature) {
        echo "  ❌ No device signature found\n\n";
        continue;
    }
    
    // 1. Format device ID with leading zeros (10 digits)
    $formattedDeviceId = str_pad($receipt->device_id, 10, '0', STR_PAD_LEFT);
    
    // 2. Format receipt date as ddMMyyyy (8 digits)
    $formattedDate = date('dmY', strtotime($receipt->receipt_date));
    
    // 3. Format receipt global number with leading zeros (10 digits)
    $formattedGlobalNo = str_pad($receipt->receipt_global_no, 10, '0', STR_PAD_LEFT);
    
    // 4. Generate receiptQrData from MD5 of device signature (first 16 hex chars)
    // CORRECT: MD5(base64_decode(signature))
    $signatureBinary = base64_decode($deviceSignature);
    $md5Hash = md5($signatureBinary);
    $receiptQrData = strtoupper(substr($md5Hash, 0, 16));
    
    // Create formatted verification code
    $verificationCode = sprintf(
        '%s-%s-%s-%s',
        substr($receiptQrData, 0, 4),
        substr($receiptQrData, 4, 4),
        substr($receiptQrData, 8, 4),
        substr($receiptQrData, 12, 4)
    );
    
    // Build QR string
    $qrCodeString = rtrim($zimraConfig->qr_url, '/') . '/' . 
        $formattedDeviceId . 
        $formattedDate . 
        $formattedGlobalNo . 
        $receiptQrData;
    
    echo "  Device ID: {$formattedDeviceId}\n";
    echo "  Date: {$formattedDate}\n";
    echo "  Global No: {$formattedGlobalNo}\n";
    echo "  QR Data (MD5): {$receiptQrData}\n";
    echo "  Verification Code: {$verificationCode}\n";
    
    // Verify length
    $dataString = $formattedDeviceId . $formattedDate . $formattedGlobalNo . $receiptQrData;
    echo "  Data Length: " . strlen($dataString) . " (must be 44)\n";
    
    if (strlen($dataString) !== 44) {
        echo "  ❌ ERROR: Data length is not 44!\n\n";
        continue;
    }
    
    // Update receipt
    $receipt->update([
        'receipt_qr_code' => $qrCodeString,
        'verification_code' => $verificationCode,
    ]);
    
    echo "  ✓ Updated\n";
    echo "  QR: {$qrCodeString}\n\n";
}

echo "Done!\n";
