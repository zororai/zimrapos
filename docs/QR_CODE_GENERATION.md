# ZIMRA QR Code Generation – Corrected Implementation Guide

This document explains how QR codes are generated for ZIMRA fiscal receipts.

## Overview

The QR code on a ZIMRA fiscal receipt allows customers to verify the receipt on the ZIMRA portal. When scanned, the QR code opens a URL that displays the receipt verification information including a **verification code**.

> **Important:** The verification code is **NOT** generated locally. It is only displayed when the QR code is scanned and the ZIMRA portal verifies the receipt.

---

## 🚨 CRITICAL RULES

### ❌ DO NOT use `getStatus` values for QR generation

`getStatus` returns `lastReceiptGlobalNo` and `lastFiscalDayNo` which are **informational only**.

The QR **MUST** use the exact values used when submitting the receipt.

### ❌ DO NOT rebuild QR dynamically in Blade

QR must be built **immediately** after successful `submitReceipt` and stored permanently.

Never rebuild it later using `$receipt->zimra_response["data"]["receiptID"]` - this causes mismatches.

---

## QR Code URL Format

```
{qrUrl}?deviceID={deviceID}&receiptID={receiptID}&fiscalDayNo={fiscalDayNo}&receiptGlobalNo={receiptGlobalNo}
```

### Parameters

| Parameter | Source | Description |
|-----------|--------|-------------|
| `qrUrl` | `getConfig` response | Base URL for receipt verification |
| `deviceID` | `zimra_configs.device_id` | The registered device ID |
| `receiptID` | `submitReceipt` **response** | Unique receipt ID returned by ZIMRA |
| `fiscalDayNo` | `submitReceipt` **request** | Exact value used in submission |
| `receiptGlobalNo` | `submitReceipt` **request** | Exact value used in submission |

---

## ✅ Correct QR Code Generation Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                 CORRECT QR Code Generation Flow                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. Device Registration                                          │
│     └── Store device_id in zimra_configs                        │
│                                                                  │
│  2. Call getConfig                                               │
│     └── Store qr_url in zimra_configs                           │
│                                                                  │
│  3. Call openDay                                                 │
│     └── Store fiscalDayNo                                       │
│                                                                  │
│  4. Submit Receipt                                               │
│     ├── Use fiscalDayNo and receiptGlobalNo from REQUEST        │
│     ├── Get receiptID from RESPONSE                             │
│     ├── Build QR string IMMEDIATELY                             │
│     └── Store QR string PERMANENTLY                             │
│                                                                  │
│  5. Generate QR Image                                            │
│     └── Use ONLY the stored QR string                           │
│     └── NO fallback logic                                       │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Implementation Details

### Correct Controller Implementation

```php
// In ZimraController::submitReceipt()

$receiptID = $result['data']['receiptID'] ?? null;

if (!$receiptID) {
    throw new \Exception("ZIMRA receipt not accepted. QR cannot be generated.");
}

$config = ZimraConfig::getActive();

// CRITICAL: Use exact values from submitReceipt REQUEST, not getStatus
$fiscalDayNo = $data['fiscalDayNo'];
$receiptGlobalNo = $data['receiptGlobalNo'];

// Validate QR requirements
if (!$config->qr_url) {
    throw new \Exception("QR generation failed: Missing qr_url. Call getConfig first.");
}

// Build QR string IMMEDIATELY with submitted values
$qrString = $config->qr_url .
    '?deviceID=' . $config->device_id .
    '&receiptID=' . $receiptID .
    '&fiscalDayNo=' . $fiscalDayNo .
    '&receiptGlobalNo=' . $receiptGlobalNo;

// Save permanently
$receipt = Receipt::create([
    // other fields
    'fiscal_day_no' => $fiscalDayNo,
    'receipt_global_no' => $receiptGlobalNo,
    'receipt_qr_code' => $qrString,
]);
```

### Correct Blade Implementation

**NO fallback logic - ONLY use stored QR string:**

```html
<!-- In receipts/pdf.blade.php -->
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>

<div id="qrcode"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ONLY use stored QR string - NO dynamic fallback
    var qrString = '{{ $receipt->receipt_qr_code ?? "" }}';
    
    if (qrString && typeof qrcode !== 'undefined') {
        var qr = qrcode(0, 'M');
        qr.addData(qrString);
        qr.make();
        document.getElementById('qrcode').innerHTML = qr.createImgTag(4);
    } else {
        document.getElementById('qrcode').innerHTML = 
            '<p>QR not available - receipt was submitted before getConfig was called</p>';
    }
});
</script>
```

## Database Schema

### zimra_configs Table

| Column | Type | Description |
|--------|------|-------------|
| `device_id` | integer | Registered device ID |
| `qr_url` | string | Base URL from getConfig |
| `fiscal_day_status` | string | Current fiscal day status |
| `last_receipt_global_no` | integer | Last global receipt number |
| `last_fiscal_day_no` | integer | Last fiscal day number |

### receipts Table

| Column | Type | Description |
|--------|------|-------------|
| `receipt_qr_code` | string | Full QR URL string |
| `fiscal_day_no` | integer | Fiscal day when receipt was issued |
| `receipt_global_no` | integer | Global receipt number |
| `zimra_response` | json | Full ZIMRA response including receiptID |

## 🚨 Common Errors

| Problem | Cause | Fix |
|---------|-------|-----|
| Receipt not found | QR built with wrong fiscalDayNo | Use submitReceipt values |
| Verification code missing | Using production portal with test receipt | Use test portal |
| Portal says invalid receipt | Signature invalid | Fix receipt hash/signature |
| QR works sometimes | Using getStatus numbers | Remove getStatus from QR logic |
| 401 Unauthorized | Certificate missing | Attach client cert properly |

---

## Test Environment Rule

If using API:
```
https://fdmsapitest.zimra.co.zw
```

Then QR must open:
```
https://fdmstest.zimra.co.zw
```

**Never mix production and test.**

---

## Troubleshooting

### QR Code Not Displaying

1. **Check if `qr_url` is set:**
   ```bash
   php artisan tinker --execute="echo App\Models\ZimraConfig::first()->qr_url;"
   ```
   If NULL, click **"Get Config"** button.

2. **Check if receipt has `receiptID`:**
   The receipt must have been successfully submitted to ZIMRA and received a `receiptID` in the response.

3. **Verify JavaScript library loaded:**
   Check browser console for errors loading `qrcode.min.js`.

---

## ✅ Final Checklist

- [ ] `getConfig` called
- [ ] `qr_url` stored
- [ ] `openDay` called
- [ ] Receipt submitted successfully
- [ ] `receiptID` returned
- [ ] QR built immediately using REQUEST values
- [ ] QR stored permanently
- [ ] Blade uses stored QR only (no fallback)

---

## Example QR URL

```
https://fdmstest.zimra.co.zw/verify?deviceID=32558&receiptID=10685875&fiscalDayNo=1&receiptGlobalNo=3
```

When scanned, this opens the ZIMRA test portal which displays the receipt verification information and the verification code.
