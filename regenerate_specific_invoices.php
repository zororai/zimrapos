<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Receipt;
use App\Models\ZimraConfig;

echo "=== Regenerating QR Codes for Specific Invoices ===\n\n";

// Target invoices
$targetInvoices = ['INV-188', 'DN-1772821028', 'INV-187', 'CN-1772819143-c1ec'];

echo "Looking for invoices: " . implode(', ', $targetInvoices) . "\n\n";

foreach ($targetInvoices as $invoiceNo) {
    echo "Processing: {$invoiceNo}\n";
    
    // Find receipt by invoice number
    $receipt = Receipt::where('invoice_no', $invoiceNo)
        ->whereNotNull('fdms_receipt_id')
        ->whereNotNull('receipt_signature')
        ->first();
    
    if (!$receipt) {
        echo "  ❌ Receipt not found or missing FDMS data\n\n";
        continue;
    }
    
    echo "  Found Receipt ID: {$receipt->id}\n";
    echo "  FDMS Receipt ID: {$receipt->fdms_receipt_id}\n";
    echo "  Status: {$receipt->status}\n";
    
    // Get ZIMRA config
    $zimraConfig = ZimraConfig::where('device_id', $receipt->device_id)->first();
    
    if (!$zimraConfig || !$zimraConfig->qr_url) {
        echo "  ❌ No ZIMRA config or QR URL for device {$receipt->device_id}\n\n";
        continue;
    }
    
    // Get device signature from receipt_signature field
    $deviceSignature = $receipt->receipt_signature['signature'] ?? null;
    
    if (!$deviceSignature) {
        echo "  ❌ No device signature found in receipt_signature\n\n";
        continue;
    }
    
    // FDMS v7.2 QR Code Generation
    
    // 1. Format device ID with leading zeros (10 digits)
    $formattedDeviceId = str_pad($receipt->device_id, 10, '0', STR_PAD_LEFT);
    
    // 2. Format receipt date as ddMMyyyy (8 digits)
    // Use the exact date from the receipt
    $formattedDate = date('dmY', strtotime($receipt->receipt_date));
    
    // 3. Format receipt global number with leading zeros (10 digits)
    $formattedGlobalNo = str_pad($receipt->receipt_global_no, 10, '0', STR_PAD_LEFT);
    
    // 4. Generate receiptQrData from MD5 of device signature (first 16 hex chars)
    // CRITICAL: MD5(base64_decode(signature))
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
    
    // Validate component lengths
    $errors = [];
    if (strlen($formattedDeviceId) !== 10) {
        $errors[] = "Device ID must be 10 digits, got " . strlen($formattedDeviceId);
    }
    if (strlen($formattedDate) !== 8) {
        $errors[] = "Date must be 8 digits, got " . strlen($formattedDate);
    }
    if (strlen($formattedGlobalNo) !== 10) {
        $errors[] = "Global No must be 10 digits, got " . strlen($formattedGlobalNo);
    }
    if (strlen($receiptQrData) !== 16) {
        $errors[] = "QR Data must be 16 hex chars, got " . strlen($receiptQrData);
    }
    
    if (!empty($errors)) {
        echo "  ❌ Validation errors:\n";
        foreach ($errors as $error) {
            echo "    - {$error}\n";
        }
        echo "\n";
        continue;
    }
    
    // Build QR data string
    $qrDataString = $formattedDeviceId . $formattedDate . $formattedGlobalNo . $receiptQrData;
    
    // Final validation: Total must be 44 characters
    if (strlen($qrDataString) !== 44) {
        echo "  ❌ Total length must be 44 characters, got " . strlen($qrDataString) . "\n\n";
        continue;
    }
    
    // Build final QR URL
    $qrCodeString = rtrim($zimraConfig->qr_url, '/') . '/' . $qrDataString;
    
    echo "  Components:\n";
    echo "    Device ID:  {$formattedDeviceId} (10 chars)\n";
    echo "    Date:       {$formattedDate} (8 chars)\n";
    echo "    Global No:  {$formattedGlobalNo} (10 chars)\n";
    echo "    QR Data:    {$receiptQrData} (16 chars)\n";
    echo "    Total:      {$qrDataString} (44 chars) ✓\n";
    echo "  Verification Code: {$verificationCode}\n";
    echo "  QR URL: {$qrCodeString}\n";
    
    // Update receipt
    try {
        $receipt->update([
            'receipt_qr_code' => $qrCodeString,
            'verification_code' => $verificationCode,
        ]);
        echo "  ✅ QR Code updated successfully\n\n";
    } catch (\Exception $e) {
        echo "  ❌ Failed to update: {$e->getMessage()}\n\n";
    }
}

echo "Done! You can now reprint these invoices with the corrected QR codes.\n";
