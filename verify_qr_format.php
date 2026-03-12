<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Receipt;

echo "=== QR Code Format Verification ===\n\n";

// Get latest receipt
$receipt = Receipt::orderBy('id', 'desc')->first();

if (!$receipt || !$receipt->receipt_qr_code) {
    echo "No receipt with QR code found\n";
    exit(1);
}

echo "Receipt: {$receipt->invoice_no}\n";
echo "QR Code: {$receipt->receipt_qr_code}\n\n";

// Parse QR code
$parts = parse_url($receipt->receipt_qr_code);
$baseUrl = ($parts['scheme'] ?? '') . '://' . ($parts['host'] ?? '');
$path = ltrim($parts['path'] ?? '', '/');

echo "Base URL: {$baseUrl}\n";
echo "Data String: {$path}\n";
echo "Data Length: " . strlen($path) . " (must be exactly 44)\n\n";

if (strlen($path) === 44) {
    $deviceId = substr($path, 0, 10);
    $receiptDate = substr($path, 10, 8);
    $receiptGlobalNo = substr($path, 18, 10);
    $receiptQrData = substr($path, 28, 16);
    
    echo "✓ Correct length (44 characters)\n\n";
    echo "Components:\n";
    echo "  Device ID (10):       '{$deviceId}' [" . strlen($deviceId) . " chars]\n";
    echo "  Receipt Date (8):     '{$receiptDate}' [" . strlen($receiptDate) . " chars]\n";
    echo "  Receipt Global No (10): '{$receiptGlobalNo}' [" . strlen($receiptGlobalNo) . " chars]\n";
    echo "  Receipt QR Data (16): '{$receiptQrData}' [" . strlen($receiptQrData) . " chars]\n\n";
    
    // Verify each component
    $errors = [];
    
    if (strlen($deviceId) !== 10) {
        $errors[] = "Device ID must be 10 digits, got " . strlen($deviceId);
    }
    
    if (strlen($receiptDate) !== 8) {
        $errors[] = "Receipt Date must be 8 digits, got " . strlen($receiptDate);
    }
    
    if (strlen($receiptGlobalNo) !== 10) {
        $errors[] = "Receipt Global No must be 10 digits, got " . strlen($receiptGlobalNo);
    }
    
    if (strlen($receiptQrData) !== 16) {
        $errors[] = "Receipt QR Data must be 16 hex chars, got " . strlen($receiptQrData);
    }
    
    if (empty($errors)) {
        echo "✓ All components have correct length\n\n";
        
        // Verify against database values
        echo "Database Verification:\n";
        echo "  DB Device ID: {$receipt->device_id} → Formatted: " . str_pad($receipt->device_id, 10, '0', STR_PAD_LEFT) . "\n";
        echo "  DB Global No: {$receipt->receipt_global_no} → Formatted: " . str_pad($receipt->receipt_global_no, 10, '0', STR_PAD_LEFT) . "\n";
        echo "  DB Receipt Date: {$receipt->receipt_date} → Formatted: " . date('dmY', strtotime($receipt->receipt_date)) . "\n";
        
        $expectedDeviceId = str_pad($receipt->device_id, 10, '0', STR_PAD_LEFT);
        $expectedGlobalNo = str_pad($receipt->receipt_global_no, 10, '0', STR_PAD_LEFT);
        $expectedDate = date('dmY', strtotime($receipt->receipt_date));
        
        if ($deviceId === $expectedDeviceId && $receiptGlobalNo === $expectedGlobalNo && $receiptDate === $expectedDate) {
            echo "\n✓ QR code matches database values\n";
            echo "\n✅ QR CODE IS CORRECTLY FORMATTED\n";
        } else {
            echo "\n❌ QR code does NOT match database values:\n";
            if ($deviceId !== $expectedDeviceId) echo "  Device ID mismatch: '{$deviceId}' vs '{$expectedDeviceId}'\n";
            if ($receiptGlobalNo !== $expectedGlobalNo) echo "  Global No mismatch: '{$receiptGlobalNo}' vs '{$expectedGlobalNo}'\n";
            if ($receiptDate !== $expectedDate) echo "  Date mismatch: '{$receiptDate}' vs '{$expectedDate}'\n";
        }
    } else {
        echo "❌ Component length errors:\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
    }
} else {
    echo "❌ INCORRECT LENGTH\n";
    echo "Expected: 44 characters\n";
    echo "Got: " . strlen($path) . " characters\n\n";
    
    if (strlen($path) > 44) {
        echo "Extra characters: " . (strlen($path) - 44) . "\n";
        echo "This will cause 'Invoice not found' error\n";
    }
}
