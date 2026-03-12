<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Receipt;
use App\Models\ZimraConfig;
use Illuminate\Support\Facades\Log;

echo "=== QR Code Regeneration for INV-195 ===\n\n";

// Find receipt by invoice number
$receipt = Receipt::where('invoice_no', 'INV-195')->first();

if (!$receipt) {
    echo "❌ Receipt INV-195 not found\n";
    exit(1);
}

echo "✓ Found receipt:\n";
echo "  ID: {$receipt->id}\n";
echo "  Invoice: {$receipt->invoice_no}\n";
echo "  Device ID: {$receipt->device_id}\n";
echo "  Receipt Global No: {$receipt->receipt_global_no}\n";
echo "  Receipt Date: {$receipt->receipt_date}\n";
echo "  FDMS Receipt ID: {$receipt->fdms_receipt_id}\n";
echo "  Receipt Hash (Device Signature): {$receipt->receipt_hash}\n\n";

// Get ZIMRA config
$zimraConfig = ZimraConfig::where('device_id', $receipt->device_id)->first();

if (!$zimraConfig) {
    echo "❌ ZIMRA config not found for device {$receipt->device_id}\n";
    exit(1);
}

echo "✓ ZIMRA Config:\n";
echo "  QR URL: {$zimraConfig->qr_url}\n\n";

// Regenerate QR code using device signature hash
if ($receipt->receipt_hash) {
    echo "Regenerating QR code...\n";
    
    // 1. Format device ID with leading zeros (10 digits)
    $formattedDeviceId = str_pad($receipt->device_id, 10, '0', STR_PAD_LEFT);
    
    // 2. Format receipt date as ddMMyyyy (8 digits)
    $receiptDate = $receipt->receipt_date;
    $formattedDate = date('dmY', strtotime($receiptDate));
    
    // 3. Format receipt global number with leading zeros (10 digits)
    $formattedGlobalNo = str_pad($receipt->receipt_global_no, 10, '0', STR_PAD_LEFT);
    
    // 4. Generate receiptQrData from device signature hash (first 16 hex chars)
    $deviceSignatureHash = $receipt->receipt_hash; // This is base64 encoded
    
    // Decode base64 hash and convert to hexadecimal
    $binary = base64_decode($deviceSignatureHash);
    $hex = strtoupper(bin2hex($binary));
    
    // Take first 16 hex characters
    $receiptQrData = substr($hex, 0, 16);
    
    // Create formatted verification code for display (with dashes)
    $verificationCode = sprintf(
        '%s-%s-%s-%s',
        substr($receiptQrData, 0, 4),
        substr($receiptQrData, 4, 4),
        substr($receiptQrData, 8, 4),
        substr($receiptQrData, 12, 4)
    );
    
    // Build QR string using FDMS concatenated format
    $qrCodeString = rtrim($zimraConfig->qr_url, '/') . '/' . 
        $formattedDeviceId . 
        $formattedDate . 
        $formattedGlobalNo . 
        $receiptQrData;
    
    echo "QR Code Components:\n";
    echo "  Device ID (10 digits): {$formattedDeviceId}\n";
    echo "  Receipt Date (ddMMyyyy): {$formattedDate}\n";
    echo "  Receipt Global No (10 digits): {$formattedGlobalNo}\n";
    echo "  Device Signature Hash (base64): {$deviceSignatureHash}\n";
    echo "  Device Signature Hash (hex): {$hex}\n";
    echo "  Receipt QR Data (first 16 hex): {$receiptQrData}\n";
    echo "  Verification Code: {$verificationCode}\n\n";
    
    echo "Generated QR Code String:\n";
    echo "  {$qrCodeString}\n\n";
    
    // Update receipt with new QR code
    $receipt->update([
        'receipt_qr_code' => $qrCodeString,
        'verification_code' => $verificationCode,
    ]);
    
    echo "✓ QR code regenerated and saved to receipt!\n\n";
    
    echo "Test the QR code at:\n";
    echo "  {$qrCodeString}\n";
    
} else {
    echo "❌ Receipt has no device signature hash - cannot regenerate QR code\n";
    exit(1);
}
