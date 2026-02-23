# Buyer Data Usage Guide

## Overview

The `buyerData` field allows you to include customer information in receipts, which is particularly useful for **FiscalInvoice** receipts where customer details are required for tax compliance.

## When to Use Buyer Data

- **FiscalInvoice**: Required for B2B transactions or when issuing tax invoices
- **FiscalReceipt**: Optional, but can be included for record-keeping
- **Credit/Debit Notes**: Include original buyer data for reference

## Buyer Data Structure

```json
{
  "buyerData": {
    "buyerRegisterName": "ABC Company Ltd",
    "buyerTradeName": "ABC Store",
    "vatNumber": "12345678",
    "buyerTIN": "1234567890",
    "buyerContacts": {
      "phoneNo": "+263712345678",
      "email": "customer@example.com"
    },
    "buyerAddress": {
      "province": "Harare",
      "city": "Harare",
      "street": "Main Street",
      "houseNo": "123",
      "district": "CBD"
    }
  }
}
```

## Field Descriptions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `buyerRegisterName` | string | No | Legal registered name of the buyer |
| `buyerTradeName` | string | No | Trading name or business name |
| `vatNumber` | string | No | VAT registration number |
| `buyerTIN` | string | No | Tax Identification Number (TIN) |
| `buyerContacts` | object | No | Contact information |
| `buyerContacts.phoneNo` | string | No | Phone number |
| `buyerContacts.email` | string | No | Email address |
| `buyerAddress` | object | No | Physical address |
| `buyerAddress.province` | string | No | Province/State |
| `buyerAddress.city` | string | No | City |
| `buyerAddress.street` | string | No | Street name |
| `buyerAddress.houseNo` | string | No | House/Building number |
| `buyerAddress.district` | string | No | District/Area |

## Usage Examples

### Example 1: Complete Receipt with Buyer Data

```php
$receiptData = [
    'receiptType' => 'FiscalInvoice',
    'receiptCurrency' => 'USD',
    'invoiceNo' => 'INV-2026-001',
    'receiptDate' => '2026-02-23T10:30:00',
    'receiptLines' => [
        [
            'receiptLineNo' => 1,
            'receiptLineType' => 'Sale',
            'receiptLineName' => 'Product A',
            'receiptLinePrice' => 100.00,
            'receiptLineQuantity' => 2.0,
            'receiptLineHSCode' => '12345678',
            'taxPercent' => 15.0,
        ],
    ],
    'receiptPayments' => [
        [
            'moneyTypeCode' => 'Cash',
            'paymentAmount' => 230.00,
        ],
    ],
    'buyerData' => [
        'buyerRegisterName' => 'ABC Company Ltd',
        'buyerTradeName' => 'ABC Store',
        'vatNumber' => '12345678',
        'buyerTIN' => '1234567890',
        'buyerContacts' => [
            'phoneNo' => '+263712345678',
            'email' => 'customer@example.com',
        ],
        'buyerAddress' => [
            'province' => 'Harare',
            'city' => 'Harare',
            'street' => 'Main Street',
            'houseNo' => '123',
            'district' => 'CBD',
        ],
    ],
];

// Submit receipt
$zimraService = new ZimraDeviceService();
$result = $zimraService->submitReceipt($receiptData);
```

### Example 2: Minimal Buyer Data

If you only have basic customer information:

```php
$receiptData = [
    // ... other receipt fields ...
    'buyerData' => [
        'buyerRegisterName' => 'John Doe',
        'buyerContacts' => [
            'phoneNo' => '+263712345678',
        ],
    ],
];
```

### Example 3: B2B Transaction with Full Details

```php
$receiptData = [
    'receiptType' => 'FiscalInvoice',
    'receiptCurrency' => 'USD',
    'invoiceNo' => 'INV-2026-B2B-001',
    'receiptDate' => '2026-02-23T14:00:00',
    'receiptLines' => [
        [
            'receiptLineNo' => 1,
            'receiptLineType' => 'Sale',
            'receiptLineName' => 'Office Supplies',
            'receiptLinePrice' => 500.00,
            'receiptLineQuantity' => 10.0,
            'receiptLineHSCode' => '48201000',
            'taxPercent' => 15.0,
        ],
    ],
    'receiptPayments' => [
        [
            'moneyTypeCode' => 'Card',
            'paymentAmount' => 5750.00,
        ],
    ],
    'buyerData' => [
        'buyerRegisterName' => 'XYZ Corporation (Pvt) Ltd',
        'buyerTradeName' => 'XYZ Corp',
        'vatNumber' => '87654321',
        'buyerTIN' => '0987654321',
        'buyerContacts' => [
            'phoneNo' => '+263242123456',
            'email' => 'accounts@xyzcorp.co.zw',
        ],
        'buyerAddress' => [
            'province' => 'Harare',
            'city' => 'Harare',
            'street' => 'Enterprise Road',
            'houseNo' => 'Block 5',
            'district' => 'Msasa',
        ],
    ],
];

$result = $zimraService->submitReceipt($receiptData);
```

### Example 4: Receipt Without Buyer Data

For simple retail transactions, buyer data is optional:

```php
$receiptData = [
    'receiptType' => 'FiscalReceipt',
    'receiptCurrency' => 'USD',
    'invoiceNo' => 'RCT-2026-001',
    'receiptDate' => '2026-02-23T09:00:00',
    'receiptLines' => [
        [
            'receiptLineNo' => 1,
            'receiptLineType' => 'Sale',
            'receiptLineName' => 'Bread',
            'receiptLinePrice' => 2.50,
            'receiptLineQuantity' => 1.0,
            'receiptLineHSCode' => '19059000',
            'taxPercent' => 0.0,
        ],
    ],
    'receiptPayments' => [
        [
            'moneyTypeCode' => 'Cash',
            'paymentAmount' => 2.50,
        ],
    ],
    // No buyerData needed for simple retail
];

$result = $zimraService->submitReceipt($receiptData);
```

## API Endpoint Usage

When submitting via API, include buyer data in the request body:

```bash
POST /api/zimra/submit-receipt
Content-Type: application/json

{
  "receiptType": "FiscalInvoice",
  "receiptCurrency": "USD",
  "invoiceNo": "INV-2026-001",
  "receiptDate": "2026-02-23T10:30:00",
  "receiptLines": [...],
  "receiptPayments": [...],
  "buyerData": {
    "buyerRegisterName": "ABC Company Ltd",
    "buyerTradeName": "ABC Store",
    "vatNumber": "12345678",
    "buyerTIN": "1234567890",
    "buyerContacts": {
      "phoneNo": "+263712345678",
      "email": "customer@example.com"
    },
    "buyerAddress": {
      "province": "Harare",
      "city": "Harare",
      "street": "Main Street",
      "houseNo": "123",
      "district": "CBD"
    }
  }
}
```

## Database Storage

Buyer data is stored in the `receipts` table as a JSON field:

```sql
SELECT 
    id,
    invoice_no,
    receipt_type,
    buyer_data->>'$.buyerRegisterName' as buyer_name,
    buyer_data->>'$.vatNumber' as buyer_vat,
    receipt_total
FROM receipts
WHERE buyer_data IS NOT NULL;
```

### Querying Buyer Data

```php
// Find receipts by buyer VAT number
$receipts = Receipt::whereNotNull('buyer_data')
    ->whereRaw("JSON_EXTRACT(buyer_data, '$.vatNumber') = ?", ['12345678'])
    ->get();

// Find receipts by buyer name
$receipts = Receipt::whereNotNull('buyer_data')
    ->whereRaw("JSON_EXTRACT(buyer_data, '$.buyerRegisterName') LIKE ?", ['%ABC Company%'])
    ->get();

// Get all receipts with buyer data
$receipts = Receipt::whereNotNull('buyer_data')->get();

foreach ($receipts as $receipt) {
    $buyerName = $receipt->buyer_data['buyerRegisterName'] ?? 'N/A';
    $buyerVat = $receipt->buyer_data['vatNumber'] ?? 'N/A';
    echo "Receipt: {$receipt->invoice_no} - Buyer: {$buyerName} (VAT: {$buyerVat})\n";
}
```

## Validation

ZIMRA validates buyer data according to FDMS specifications:

- **RCPT006**: Invalid buyer data format
- **RCPT034**: Invalid VAT number format
- **RCPT035**: Invalid TIN format

### Common Validation Issues

1. **Invalid VAT Number Format**
   - Ensure VAT numbers follow Zimbabwe format
   - Typically 8 digits

2. **Invalid TIN Format**
   - TIN should be 10 digits
   - No special characters

3. **Missing Required Fields**
   - For FiscalInvoice, buyer name is often required
   - Check ZIMRA requirements for your business type

## Best Practices

1. **Always Include for B2B Transactions**
   - Required for proper tax compliance
   - Helps with audit trails

2. **Validate Before Submission**
   ```php
   function validateBuyerData($buyerData) {
       if (isset($buyerData['vatNumber'])) {
           if (!preg_match('/^\d{8}$/', $buyerData['vatNumber'])) {
               throw new \Exception('Invalid VAT number format');
           }
       }
       
       if (isset($buyerData['buyerTIN'])) {
           if (!preg_match('/^\d{10}$/', $buyerData['buyerTIN'])) {
               throw new \Exception('Invalid TIN format');
           }
       }
       
       return true;
   }
   ```

3. **Store Customer Data Separately**
   - Maintain a customers table
   - Reference customer ID in your POS system
   - Build buyer data from customer record when submitting

4. **Handle Optional Fields Gracefully**
   - Only include fields you have data for
   - Don't send empty strings or null values

5. **Privacy Considerations**
   - Ensure GDPR/POPIA compliance
   - Only collect necessary customer data
   - Secure storage of personal information

## Troubleshooting

### Issue: RCPT006 - Invalid Buyer Data

**Solution**: Check that all fields are properly formatted and follow ZIMRA specifications.

```php
// Correct format
$buyerData = [
    'buyerRegisterName' => 'ABC Company Ltd', // String
    'vatNumber' => '12345678', // 8 digits
    'buyerTIN' => '1234567890', // 10 digits
];

// Incorrect format
$buyerData = [
    'buyerRegisterName' => '', // Empty string - omit instead
    'vatNumber' => 'VAT12345678', // Contains letters
    'buyerTIN' => 123456789, // Number instead of string
];
```

### Issue: Buyer Data Not Saved to Database

**Solution**: Ensure migration has been run:

```bash
php artisan migrate
```

Check that the `buyer_data` column exists:

```sql
DESCRIBE receipts;
```

## Receipt Printing

### Buyer Data on Printed Receipts

When buyer data is included in a receipt, it will **automatically appear on the printed receipt PDF**. The receipt template displays:

- **Customer Name** (buyerRegisterName)
- **Trading Name** (buyerTradeName)
- **VAT Number**
- **TIN**
- **Contact Information** (phone and email)
- **Full Address** (formatted from address components)

### Example Printed Receipt Layout

```
┌─────────────────────────────────────┐
│        FISCAL INVOICE               │
│     ZIMRA Compliant Receipt         │
├─────────────────────────────────────┤
│ Invoice No: INV-2026-001            │
│ Date: 23 Feb 2026 10:30            │
│ Receipt Type: FiscalInvoice         │
│ Device ID: 32558                    │
│ Fiscal Day: 10                      │
│ Receipt Counter: 15                 │
├─────────────────────────────────────┤
│        CUSTOMER DETAILS             │
│ Name: ABC Company Ltd               │
│ Trading Name: ABC Store             │
│ VAT Number: 12345678                │
│ TIN: 1234567890                     │
│ Phone: +263712345678                │
│ Email: customer@example.com         │
│ Address: 123, Main Street, CBD,     │
│          Harare, Harare             │
├─────────────────────────────────────┤
│ ITEMS                               │
│ Product A    2 × $100.00  $200.00   │
├─────────────────────────────────────┤
│ Tax Information                     │
│ Tax Code: A (15%)                   │
│ Tax Amount: $30.00                  │
├─────────────────────────────────────┤
│ TOTAL: $230.00                      │
├─────────────────────────────────────┤
│ ZIMRA Fiscal Data                   │
│ Verification Code: ABC123           │
│ [QR CODE]                           │
└─────────────────────────────────────┘
```

### Conditional Display

- Buyer details section **only appears if buyer_data is present**
- Individual fields are shown only if they have values
- Empty or null fields are automatically hidden
- Address is formatted intelligently from available components

### Accessing Receipt PDF

```php
// Generate receipt PDF
$receiptId = $receipt->id;
$pdfUrl = route('zimra.receipt.pdf', ['id' => $receiptId]);

// Or via controller
return redirect()->route('zimra.receipt.pdf', ['id' => $receipt->id]);
```

### Customizing Receipt Template

The receipt template is located at:
```
resources/views/receipts/pdf.blade.php
```

You can customize the buyer data section styling by modifying the template. The buyer data section uses:
- Light blue background (`#f0f8ff`)
- Blue header color (`#1976d2`)
- Responsive layout that works on thermal printers

## Related Documentation

- [SYSTEM_ARCHITECTURE.md](./SYSTEM_ARCHITECTURE.md) - Complete system architecture
- [ZIMRA_TAX_CALCULATION_EXAMPLES.md](./ZIMRA_TAX_CALCULATION_EXAMPLES.md) - Tax calculation examples
- FDMS API Specification - Section 4.7 (submitReceipt)

---

**Last Updated**: 2026-02-23  
**Version**: 1.0
