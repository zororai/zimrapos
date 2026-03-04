# ZIMRA QR Code Implementation Guide

## Overview

This document provides a complete guide for implementing QR code generation for ZIMRA fiscal receipts using the `simplesoftwareio/simple-qrcode` package.

## Architecture

### Components

1. **ReceiptQrCodeService** - Service class for QR code generation
2. **ZimraDeviceService** - Integrated QR generation after receipt submission
3. **Database Fields** - Storage for QR images and verification codes
4. **PDF Template** - Display QR code on printed receipts

---

## 1. ReceiptQrCodeService

**Location:** `app/Services/ReceiptQrCodeService.php`

### Features

- Generates PNG QR code images from FDMS verification URLs
- Stores QR codes in `storage/app/public/zimra/qrcodes`
- Returns public URL for display
- Extracts and formats verification codes
- File naming: `qr_{receiptID}.png`

### Methods

#### `generateQrCode(string $verificationUrl, string $receiptId): array`

Generates a QR code image and returns:
```php
[
    'qr_url' => '/storage/zimra/qrcodes/qr_10699803.png',
    'verification_code' => 'C68D-0B72-287A-B159',
    'qr_path' => 'public/zimra/qrcodes/qr_10699803.png'
]
```

**QR Code Settings:**
- Format: PNG
- Size: 200x200 pixels
- Margin: 1

#### `deleteQrCode(string $receiptId): bool`

Deletes QR code file for a specific receipt.

#### `qrCodeExists(string $receiptId): bool`

Checks if QR code exists for a receipt.

#### `getQrCodeUrl(string $receiptId): ?string`

Gets QR code URL for existing receipt.

---

## 2. Integration with ZimraDeviceService

### Constructor Injection

```php
protected ReceiptQrCodeService $qrCodeService;

public function __construct(ReceiptQrCodeService $qrCodeService)
{
    $this->qrCodeService = $qrCodeService;
}
```

### QR Generation Flow

After successful `submitReceipt` response:

```php
// 1. Build verification URL
$qrCodeString = $zimraConfig->qr_url .
    '?deviceID=' . $deviceId .
    '&receiptID=' . $fdmsReceiptId .
    '&fiscalDayNo=' . $fiscalDayNo .
    '&receiptGlobalNo=' . $receiptData['receiptGlobalNo'];

// 2. Generate QR code image
$qrData = $this->qrCodeService->generateQrCode($qrCodeString, $fdmsReceiptId);
$qrCodeImageUrl = $qrData['qr_url'];
$verificationCode = $qrData['verification_code'];

// 3. Save to database
$receipt = Receipt::create([
    'receipt_qr_code' => $qrCodeString,
    'qr_url' => $qrCodeImageUrl,
    'verification_code' => $verificationCode,
    'fdms_receipt_id' => $fdmsReceiptId,
    // ... other fields
]);
```

---

## 3. Database Schema

### Migration: `add_qr_url_to_receipts_table`

```php
Schema::table('receipts', function (Blueprint $table) {
    $table->string('qr_url')->nullable()->after('receipt_qr_code');
});
```

### Receipt Model Fields

```php
protected $fillable = [
    // ... existing fields
    'receipt_qr_code',      // Full verification URL
    'qr_url',               // Path to QR image
    'verification_code',    // Formatted code (XXXX-XXXX-XXXX-XXXX)
    'fdms_receipt_id',      // FDMS receipt ID
];
```

---

## 4. PDF Template Integration

**Location:** `resources/views/receipts/pdf.blade.php`

### Display QR Code Image

```blade
<div class="verification-section" style="flex: 1; text-align: right;">
    <div class="verification-code">
        <strong>Verification code</strong><br>
        @if($receipt->verification_code)
            <span style="font-size: 10pt; font-weight: bold; letter-spacing: 1px;">
                {{ $receipt->verification_code }}
            </span><br>
        @endif
        @if($receipt->receipt_qr_code)
            <a href="{{ $receipt->receipt_qr_code }}" style="font-size: 7pt;">
                {{ $receipt->receipt_qr_code }}
            </a>
        @endif
    </div>
    @if($receipt->qr_url)
    <div class="qr-code" style="margin-top: 10px;">
        <img src="{{ public_path(str_replace('/storage', 'storage', $receipt->qr_url)) }}" 
             width="150" 
             alt="QR Code">
    </div>
    @endif
</div>
```

---

## 5. Storage Setup

### Create Symbolic Link

```bash
php artisan storage:link
```

This creates a symbolic link from `public/storage` to `storage/app/public`.

### Directory Structure

```
storage/
└── app/
    └── public/
        └── zimra/
            └── qrcodes/
                ├── qr_10699803.png
                ├── qr_10699804.png
                └── ...
```

### Public Access

QR codes are accessible at:
```
https://yourdomain.com/storage/zimra/qrcodes/qr_10699803.png
```

---

## 6. Controller Usage Example

```php
use App\Services\ZimraDeviceService;

public function submitReceipt(Request $request, ZimraDeviceService $zimra)
{
    $data = $request->all();
    
    // Get device ID from config
    $config = ZimraConfig::where('is_active', true)->first();
    $deviceId = $config->device_id;
    
    // Submit receipt (QR code generated automatically)
    $result = $zimra->submitReceipt($data, $deviceId);
    
    if (isset($result['error'])) {
        return response()->json($result, 400);
    }
    
    return response()->json($result);
}
```

---

## 7. Verification Code Format

The verification code is generated from the receipt parameters:

```php
// Input parameters
$deviceID = '32558';
$receiptID = '10699803';
$fiscalDayNo = '21';
$receiptGlobalNo = '108';

// Combined string
$codeString = '3255810699803' . '21108';

// MD5 hash (first 16 characters)
$hash = substr(md5($codeString), 0, 16);

// Formatted output
$verificationCode = 'C68D-0B72-287A-B159';
```

---

## 8. Error Handling

### QR Generation Failures

```php
try {
    $qrData = $this->qrCodeService->generateQrCode($qrCodeString, $fdmsReceiptId);
} catch (\Exception $e) {
    Log::error('QR Code Image Generation Failed', [
        'error' => $e->getMessage(),
        'receipt_id' => $fdmsReceiptId,
    ]);
    // Receipt still saved, but without QR image
}
```

### Missing QR URL

If `qr_url` is not set in `zimra_configs`:

```php
if (!$zimraConfig->qr_url) {
    Log::warning('QR Code NOT Generated', [
        'message' => 'Missing qr_url - call getConfig first',
    ]);
}
```

---

## 9. Testing

### Manual QR Code Generation

```php
use App\Services\ReceiptQrCodeService;

$qrService = app(ReceiptQrCodeService::class);

$verificationUrl = 'https://fdmstest.zimra.co.zw?deviceID=32558&receiptID=10699803&fiscalDayNo=21&receiptGlobalNo=108';
$receiptId = '10699803';

$result = $qrService->generateQrCode($verificationUrl, $receiptId);

// Output:
// [
//     'qr_url' => '/storage/zimra/qrcodes/qr_10699803.png',
//     'verification_code' => 'C68D-0B72-287A-B159',
//     'qr_path' => 'public/zimra/qrcodes/qr_10699803.png'
// ]
```

### Verify QR Code Exists

```bash
ls -la storage/app/public/zimra/qrcodes/
```

### Check Database

```sql
SELECT id, fdms_receipt_id, qr_url, verification_code 
FROM receipts 
WHERE fdms_receipt_id = '10699803';
```

---

## 10. Production Checklist

- [ ] `simplesoftwareio/simple-qrcode` package installed
- [ ] `php artisan storage:link` executed
- [ ] `storage/app/public/zimra/qrcodes` directory writable
- [ ] Migration `add_qr_url_to_receipts_table` run
- [ ] `ReceiptQrCodeService` created
- [ ] `ZimraDeviceService` constructor updated
- [ ] Receipt model has `qr_url` in fillable
- [ ] PDF template displays QR image
- [ ] Test receipt submission generates QR code
- [ ] QR code accessible via public URL
- [ ] Verification code displays correctly

---

## 11. Example Receipt Flow

```
1. User submits receipt via frontend
   ↓
2. ZimraController::submitReceipt()
   ↓
3. ZimraDeviceService::submitReceipt()
   ↓
4. FDMS returns receiptID: 10699803
   ↓
5. Build verification URL:
   https://fdmstest.zimra.co.zw?deviceID=32558&receiptID=10699803...
   ↓
6. ReceiptQrCodeService::generateQrCode()
   ↓
7. QR image saved:
   storage/app/public/zimra/qrcodes/qr_10699803.png
   ↓
8. Receipt saved with:
   - qr_url: /storage/zimra/qrcodes/qr_10699803.png
   - verification_code: C68D-0B72-287A-B159
   ↓
9. PDF generated with QR code image
   ↓
10. Customer scans QR → Opens ZIMRA portal
```

---

## 12. Troubleshooting

### QR Code Not Displaying in PDF

**Problem:** QR image not showing in PDF

**Solutions:**
1. Check `storage:link` exists: `ls -la public/storage`
2. Verify file exists: `ls storage/app/public/zimra/qrcodes/qr_*.png`
3. Check file permissions: `chmod -R 775 storage/app/public/zimra`
4. Verify `qr_url` in database is correct

### QR Code Generation Fails

**Problem:** Exception during QR generation

**Solutions:**
1. Check `simplesoftwareio/simple-qrcode` installed: `composer show simplesoftwareio/simple-qrcode`
2. Verify directory writable: `touch storage/app/public/zimra/qrcodes/test.txt`
3. Check logs: `tail -f storage/logs/laravel.log`

### Verification Code Not Showing

**Problem:** Verification code is NULL

**Solutions:**
1. Check `verification_code` in database
2. Verify QR service is called in `ZimraDeviceService`
3. Check logs for QR generation errors

---

## 13. API Reference

### FDMS Verification URL Format

```
{qrUrl}?deviceID={deviceID}&receiptID={receiptID}&fiscalDayNo={fiscalDayNo}&receiptGlobalNo={receiptGlobalNo}
```

**Example:**
```
https://fdmstest.zimra.co.zw?deviceID=32558&receiptID=10699803&fiscalDayNo=21&receiptGlobalNo=108
```

### QR Code Specifications

- **Format:** PNG
- **Size:** 200x200 pixels
- **Margin:** 1 pixel
- **Error Correction:** Medium (M)
- **Encoding:** UTF-8

---

## Summary

The QR code implementation provides:

1. ✅ Automatic QR generation after receipt submission
2. ✅ PNG image storage in organized directory
3. ✅ Formatted verification code extraction
4. ✅ PDF template integration
5. ✅ Clean service architecture
6. ✅ Error handling and logging
7. ✅ Laravel best practices (DI, storage, migrations)

The system is production-ready and follows ZIMRA FDMS specifications.
