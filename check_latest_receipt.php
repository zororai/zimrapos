<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Receipt;
use App\Models\ZimraConfig;

echo "=== Latest Receipt QR Code Analysis ===\n\n";

// Get the latest receipt
$receipt = Receipt::orderBy('id', 'desc')->first();

if (!$receipt) {
    echo "❌ No receipts found\n";
    exit(1);
}

echo "Latest Receipt:\n";
echo "  ID: {$receipt->id}\n";
echo "  Invoice: {$receipt->invoice_no}\n";
echo "  Device ID: {$receipt->device_id}\n";
echo "  Receipt Global No: {$receipt->receipt_global_no}\n";
echo "  Receipt Date: {$receipt->receipt_date}\n";
echo "  FDMS Receipt ID: {$receipt->fdms_receipt_id}\n";
echo "  Status: {$receipt->status}\n\n";

echo "Signature Data:\n";
echo "  Receipt Hash (Device Signature): " . ($receipt->receipt_hash ?? 'NULL') . "\n";
echo "  Receipt Signature: " . (isset($receipt->receipt_signature['hash']) ? $receipt->receipt_signature['hash'] : 'NULL') . "\n\n";

echo "QR Code Data:\n";
echo "  QR Code: " . ($receipt->receipt_qr_code ?? 'NULL') . "\n";
echo "  Verification Code: " . ($receipt->verification_code ?? 'NULL') . "\n\n";

// Analyze QR code if it exists
if ($receipt->receipt_qr_code) {
    $qrCode = $receipt->receipt_qr_code;
    
    // Parse QR code
    $parts = parse_url($qrCode);
    $baseUrl = ($parts['scheme'] ?? '') . '://' . ($parts['host'] ?? '');
    $path = $parts['path'] ?? '';
    
    // Remove leading slash
    $data = ltrim($path, '/');
    
    echo "QR Code Breakdown:\n";
    echo "  Base URL: {$baseUrl}\n";
    echo "  Data String: {$data}\n";
    echo "  Data Length: " . strlen($data) . " (should be 44: 10+8+10+16)\n\n";
    
    if (strlen($data) >= 44) {
        $deviceId = substr($data, 0, 10);
        $receiptDate = substr($data, 10, 8);
        $receiptGlobalNo = substr($data, 18, 10);
        $receiptQrData = substr($data, 28, 16);
        
        echo "  Device ID (10 digits): {$deviceId}\n";
        echo "  Receipt Date (ddMMyyyy): {$receiptDate}\n";
        echo "  Receipt Global No (10 digits): {$receiptGlobalNo}\n";
        echo "  Receipt QR Data (16 hex): {$receiptQrData}\n\n";
        
        // Verify device signature hash
        if ($receipt->receipt_hash) {
            $binary = base64_decode($receipt->receipt_hash);
            $hex = strtoupper(bin2hex($binary));
            $expectedQrData = substr($hex, 0, 16);
            
            echo "Verification:\n";
            echo "  Device Signature Hash (full hex): {$hex}\n";
            echo "  Expected QR Data (first 16 hex): {$expectedQrData}\n";
            echo "  Actual QR Data: {$receiptQrData}\n";
            echo "  Match: " . ($expectedQrData === $receiptQrData ? '✓ YES' : '✗ NO') . "\n\n";
            
            if ($expectedQrData !== $receiptQrData) {
                echo "❌ QR CODE MISMATCH - This will cause 'Invoice not found' error!\n";
                echo "   The QR code is using wrong hash data.\n\n";
            } else {
                echo "✓ QR code is using correct device signature hash\n\n";
            }
        }
        
        // Verify date format
        $actualDate = date('dmY', strtotime($receipt->receipt_date));
        echo "Date Verification:\n";
        echo "  Receipt Date: {$receipt->receipt_date}\n";
        echo "  Expected Format (ddMMyyyy): {$actualDate}\n";
        echo "  Actual in QR: {$receiptDate}\n";
        echo "  Match: " . ($actualDate === $receiptDate ? '✓ YES' : '✗ NO') . "\n\n";
        
        // Verify global number
        $expectedGlobalNo = str_pad($receipt->receipt_global_no, 10, '0', STR_PAD_LEFT);
        echo "Global Number Verification:\n";
        echo "  Receipt Global No: {$receipt->receipt_global_no}\n";
        echo "  Expected Format (10 digits): {$expectedGlobalNo}\n";
        echo "  Actual in QR: {$receiptGlobalNo}\n";
        echo "  Match: " . ($expectedGlobalNo === $receiptGlobalNo ? '✓ YES' : '✗ NO') . "\n\n";
    } else {
        echo "❌ QR code data string is too short (length: " . strlen($data) . ", expected: 44)\n";
    }
} else {
    echo "❌ No QR code generated for this receipt\n";
}

// Check ZIMRA response for errors
if ($receipt->zimra_response) {
    echo "\nZIMRA Response:\n";
    echo "  Receipt ID: " . ($receipt->zimra_response['receiptID'] ?? 'NULL') . "\n";
    echo "  Validation Code: " . ($receipt->validation_code ?? 'NULL') . "\n";
    
    if (isset($receipt->zimra_response['validationErrors'])) {
        echo "  Validation Errors:\n";
        foreach ($receipt->zimra_response['validationErrors'] as $error) {
            echo "    - [{$error['errorCode']}] {$error['errorMessage']}\n";
        }
    }
}
