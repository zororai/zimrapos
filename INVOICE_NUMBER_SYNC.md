# Invoice Number Sync with FDMS

## Overview

The invoice number is now **automatically synced** with the FDMS `lastReceiptGlobalNo` counter. When a user opens the "Submit Receipt" page, the invoice number field is auto-populated with the next available number from FDMS.

---

## How It Works

### 1. **User Opens Submit Receipt Page**

When the page loads, it automatically calls:
```javascript
await this.loadNextInvoiceNo();
```

### 2. **Frontend Fetches Next Invoice Number**

```javascript
async loadNextInvoiceNo() {
    try {
        const res = await fetch('/zimra/next-invoice-no');
        if (res.ok) {
            const data = await res.json();
            this.receiptForm.invoiceNo = data.invoice_no;
        }
    } catch (e) {
        console.error('Failed to load next invoice number:', e);
        this.receiptForm.invoiceNo = 'INV-001';
    }
}
```

### 3. **Backend Queries FDMS**

The endpoint `/zimra/next-invoice-no` calls:

```php
public function getNextInvoiceNo(ZimraDeviceService $zimra)
{
    $config = ZimraConfig::getActive();
    $deviceId = $config->device_id;
    
    // Get FDMS status
    $fdmsStatus = $zimra->getStatus($deviceId);
    
    // Extract lastReceiptGlobalNo and increment
    $lastGlobalNo = $fdmsStatus['lastReceiptGlobalNo'] ?? 0;
    $nextGlobalNo = $lastGlobalNo + 1;
    
    // Format as INV-{number}
    $invoiceNo = 'INV-' . str_pad($nextGlobalNo, 3, '0', STR_PAD_LEFT);
    
    return response()->json([
        'invoice_no' => $invoiceNo,
        'next_global_no' => $nextGlobalNo,
        'fdms_last_global_no' => $lastGlobalNo,
    ]);
}
```

### 4. **Invoice Number Displayed**

The invoice number field is automatically populated:
```
INV-105
```

---

## Example Flow

### FDMS Status Response
```json
{
  "fiscalDayStatus": "FiscalDayOpened",
  "lastReceiptGlobalNo": 104,
  "lastFiscalDayNo": 21,
  "operationID": "0HNJM7V0TOOUU:00000001"
}
```

### Calculation
- **FDMS lastReceiptGlobalNo:** 104
- **Next Global No:** 104 + 1 = **105**
- **Invoice Number:** **INV-105**

### API Response
```json
{
  "invoice_no": "INV-105",
  "next_global_no": 105,
  "fdms_last_global_no": 104
}
```

### UI Display
```
┌─────────────────────────────────────┐
│ Invoice No                          │
│ ┌─────────────────────────────────┐ │
│ │ INV-105                         │ │
│ └─────────────────────────────────┘ │
└─────────────────────────────────────┘
```

---

## Benefits

### ✅ Always In Sync
- Invoice number is **always** based on FDMS global counter
- No manual tracking needed
- No risk of duplicate invoice numbers

### ✅ Automatic Increment
- System automatically calculates: `lastReceiptGlobalNo + 1`
- User doesn't need to remember the last number
- Works even if local database is missing receipts

### ✅ Fallback Protection
If FDMS is unreachable, the system falls back to local database:
```php
// Fallback: use local database
$maxGlobalNo = Receipt::where('device_id', $deviceId)
    ->max('receipt_global_no') ?? 0;

$nextGlobalNo = $maxGlobalNo + 1;
$invoiceNo = 'INV-' . str_pad($nextGlobalNo, 3, '0', STR_PAD_LEFT);
```

---

## User Experience

### Before (Manual Entry)
1. User opens Submit Receipt page
2. Invoice field is empty
3. User must remember/lookup last invoice number
4. User manually types: `INV-105`
5. Risk of typos or wrong number

### After (Auto-Sync)
1. User opens Submit Receipt page
2. Invoice field **automatically shows: INV-105**
3. User can proceed immediately
4. No manual entry needed
5. Always correct number

---

## Testing

### Test 1: Normal Flow
1. Open Submit Receipt page
2. Check invoice number field
3. Should show: `INV-{lastReceiptGlobalNo + 1}`

### Test 2: After Submitting Receipt
1. Submit a receipt (e.g., INV-105)
2. Refresh the page or click "Submit Receipt" tab again
3. Invoice number should update to: `INV-106`

### Test 3: Multiple Devices
1. Switch to different company/device
2. Invoice number should reflect that device's FDMS counter
3. Each device has independent numbering

### Test 4: FDMS Unreachable
1. Disconnect from FDMS (simulate network error)
2. System should fall back to local database
3. Response includes `"fallback": true`

---

## API Endpoint

### Request
```
GET /zimra/next-invoice-no
```

### Response (Success)
```json
{
  "invoice_no": "INV-105",
  "next_global_no": 105,
  "fdms_last_global_no": 104
}
```

### Response (Fallback)
```json
{
  "invoice_no": "INV-105",
  "next_global_no": 105,
  "fallback": true
}
```

### Response (No Config)
```json
{
  "invoice_no": "INV-001"
}
```

---

## Logging

The system logs every invoice number generation:

```
[local.INFO] Next Invoice Number Generated from FDMS
{
  "device_id": 32558,
  "fdms_last_global_no": 104,
  "next_global_no": 105,
  "invoice_no": "INV-105"
}
```

If FDMS fails:
```
[local.ERROR] Failed to get next invoice number from FDMS
{
  "error": "Connection timeout",
  "device_id": 32558
}
```

---

## Code Location

### Backend
- **File:** `app/Http/Controllers/ZimraController.php`
- **Method:** `getNextInvoiceNo()`
- **Line:** ~803

### Frontend
- **File:** `resources/views/zimra.blade.php`
- **Method:** `loadNextInvoiceNo()`
- **Line:** ~1537

### Route
- **File:** `routes/web.php`
- **Route:** `GET /zimra/next-invoice-no`
- **Line:** ~40

---

## Invoice Number Format

### Pattern
```
INV-{globalNo}
```

### Examples
- Global No: 1 → **INV-001**
- Global No: 99 → **INV-099**
- Global No: 105 → **INV-105**
- Global No: 1234 → **INV-1234**

### Padding
- Numbers below 100 are padded with zeros (3 digits minimum)
- Numbers above 999 use their natural length

---

## Troubleshooting

### Issue: Invoice number shows "INV-001"
**Cause:** No active ZIMRA config or device not registered

**Solution:**
1. Check if device is registered
2. Verify `zimra_configs` table has active config
3. Check `device_id` is set

### Issue: Invoice number doesn't increment
**Cause:** FDMS not updating `lastReceiptGlobalNo`

**Solution:**
1. Check FDMS status: `GET /Device/v1/{deviceId}/GetStatus`
2. Verify fiscal day is open
3. Check if receipts are being submitted successfully

### Issue: Wrong invoice number
**Cause:** FDMS and local database out of sync

**Solution:**
1. The system uses FDMS as source of truth
2. Invoice number will auto-correct on next page load
3. Check logs for FDMS response

---

## Summary

✅ **Invoice numbers are now automatically synced with FDMS**
- No manual entry needed
- Always shows: `lastReceiptGlobalNo + 1`
- Auto-updates when page loads
- Fallback to local database if FDMS unreachable
- Fully logged for debugging

The user simply opens the Submit Receipt page and the invoice number is ready to use! 🎉
