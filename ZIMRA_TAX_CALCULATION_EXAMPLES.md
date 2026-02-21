# ZIMRA FDMS Tax Calculation Examples

## Understanding receiptLinesTaxInclusive

**CRITICAL:** The flag name is confusing but here's what it means:

| Flag Value | Meaning | Calculation |
|------------|---------|-------------|
| `true` | **Tax-EXCLUSIVE** pricing | Prices are NET, tax added on top |
| `false` | **Tax-INCLUSIVE** pricing | Prices are GROSS, tax extracted |

---

## Example 1: Single Tax Rate (receiptLinesTaxInclusive = true)

### Scenario
- Product A: 2 × $15.00 = $30.00
- Product B: 1 × $50.00 = $50.00
- Tax Rate: 15% (VAT)

### Calculation
```
Line 1 Net:  2 × 15.00 = 30.00
Line 2 Net:  1 × 50.00 = 50.00
-----------------------------------
Total Net:              80.00
Tax (15%):   80.00 × 0.15 = 12.00
-----------------------------------
Gross Total:            92.00
```

### JSON Payload
```json
{
  "receiptLinesTaxInclusive": true,
  "receiptLines": [
    {
      "receiptLineNo": 1,
      "receiptLineType": "Sale",
      "receiptLineName": "Product A",
      "receiptLineQuantity": 2.0,
      "receiptLinePrice": 15.0,
      "receiptLineTotal": 30.0,
      "taxCode": "A",
      "taxPercent": 15.0,
      "taxID": 1
    },
    {
      "receiptLineNo": 2,
      "receiptLineType": "Sale",
      "receiptLineName": "Product B",
      "receiptLineQuantity": 1.0,
      "receiptLinePrice": 50.0,
      "receiptLineTotal": 50.0,
      "taxCode": "A",
      "taxPercent": 15.0,
      "taxID": 1
    }
  ],
  "receiptTaxes": [
    {
      "taxCode": "A",
      "taxPercent": 15.0,
      "taxID": 1,
      "taxAmount": 12.0,
      "salesAmountWithTax": 92.0
    }
  ],
  "receiptTotal": 92.0,
  "receiptPayments": [
    {
      "moneyTypeCode": "Cash",
      "paymentAmount": 92.0
    }
  ]
}
```

---

## Example 2: Single Tax Rate (receiptLinesTaxInclusive = false)

### Scenario
- Product A: 2 × $17.25 = $34.50 (GROSS price)
- Product B: 1 × $57.50 = $57.50 (GROSS price)
- Tax Rate: 15% (VAT)

### Calculation
```
Line 1 Gross:  2 × 17.25 = 34.50
Line 2 Gross:  1 × 57.50 = 57.50
-----------------------------------
Total Gross:            92.00

Tax Extraction (15%):
Tax = 92.00 × 15 / (100 + 15) = 92.00 × 15 / 115 = 12.00
Net = 92.00 - 12.00 = 80.00
```

### JSON Payload
```json
{
  "receiptLinesTaxInclusive": false,
  "receiptLines": [
    {
      "receiptLineNo": 1,
      "receiptLineType": "Sale",
      "receiptLineName": "Product A",
      "receiptLineQuantity": 2.0,
      "receiptLinePrice": 17.25,
      "receiptLineTotal": 34.5,
      "taxCode": "A",
      "taxPercent": 15.0,
      "taxID": 1
    },
    {
      "receiptLineNo": 2,
      "receiptLineType": "Sale",
      "receiptLineName": "Product B",
      "receiptLineQuantity": 1.0,
      "receiptLinePrice": 57.5,
      "receiptLineTotal": 57.5,
      "taxCode": "A",
      "taxPercent": 15.0,
      "taxID": 1
    }
  ],
  "receiptTaxes": [
    {
      "taxCode": "A",
      "taxPercent": 15.0,
      "taxID": 1,
      "taxAmount": 12.0,
      "salesAmountWithTax": 92.0
    }
  ],
  "receiptTotal": 92.0,
  "receiptPayments": [
    {
      "moneyTypeCode": "Cash",
      "paymentAmount": 92.0
    }
  ]
}
```

---

## Example 3: Multiple Tax Rates (receiptLinesTaxInclusive = true)

### Scenario
- Product A: 2 × $15.00 = $30.00 (VAT 15%)
- Product B: 1 × $50.00 = $50.00 (VAT 15%)
- Product C: 3 × $10.00 = $30.00 (Zero-rated 0%)

### Calculation
```
Tax Code A (15%):
  Line 1: 30.00
  Line 2: 50.00
  Net:    80.00
  Tax:    80.00 × 0.15 = 12.00
  Gross:  92.00

Tax Code B (0%):
  Line 3: 30.00
  Net:    30.00
  Tax:    30.00 × 0.00 = 0.00
  Gross:  30.00

-----------------------------------
Total:  122.00
```

### JSON Payload
```json
{
  "receiptLinesTaxInclusive": true,
  "receiptLines": [
    {
      "receiptLineNo": 1,
      "receiptLineType": "Sale",
      "receiptLineName": "Product A",
      "receiptLineQuantity": 2.0,
      "receiptLinePrice": 15.0,
      "receiptLineTotal": 30.0,
      "taxCode": "A",
      "taxPercent": 15.0,
      "taxID": 1
    },
    {
      "receiptLineNo": 2,
      "receiptLineType": "Sale",
      "receiptLineName": "Product B",
      "receiptLineQuantity": 1.0,
      "receiptLinePrice": 50.0,
      "receiptLineTotal": 50.0,
      "taxCode": "A",
      "taxPercent": 15.0,
      "taxID": 1
    },
    {
      "receiptLineNo": 3,
      "receiptLineType": "Sale",
      "receiptLineName": "Product C",
      "receiptLineQuantity": 3.0,
      "receiptLinePrice": 10.0,
      "receiptLineTotal": 30.0,
      "taxCode": "B",
      "taxPercent": 0.0,
      "taxID": 2
    }
  ],
  "receiptTaxes": [
    {
      "taxCode": "A",
      "taxPercent": 15.0,
      "taxID": 1,
      "taxAmount": 12.0,
      "salesAmountWithTax": 92.0
    },
    {
      "taxCode": "B",
      "taxPercent": 0.0,
      "taxID": 2,
      "taxAmount": 0.0,
      "salesAmountWithTax": 30.0
    }
  ],
  "receiptTotal": 122.0,
  "receiptPayments": [
    {
      "moneyTypeCode": "Cash",
      "paymentAmount": 122.0
    }
  ]
}
```

---

## Example 4: Mixed Tax IDs (receiptLinesTaxInclusive = true)

### Scenario
- Product A: 1 × $100.00 (Standard VAT 15%, taxID=1)
- Product B: 2 × $25.00 = $50.00 (Reduced VAT 5%, taxID=3)
- Product C: 1 × $20.00 (Zero-rated 0%, taxID=2)

### Calculation
```
Tax ID 1 (15%):
  Net:    100.00
  Tax:    100.00 × 0.15 = 15.00
  Gross:  115.00

Tax ID 3 (5%):
  Net:    50.00
  Tax:    50.00 × 0.05 = 2.50
  Gross:  52.50

Tax ID 2 (0%):
  Net:    20.00
  Tax:    20.00 × 0.00 = 0.00
  Gross:  20.00

-----------------------------------
Total:  187.50
```

### JSON Payload
```json
{
  "receiptLinesTaxInclusive": true,
  "receiptLines": [
    {
      "receiptLineNo": 1,
      "receiptLineType": "Sale",
      "receiptLineName": "Product A",
      "receiptLineQuantity": 1.0,
      "receiptLinePrice": 100.0,
      "receiptLineTotal": 100.0,
      "taxCode": "A",
      "taxPercent": 15.0,
      "taxID": 1
    },
    {
      "receiptLineNo": 2,
      "receiptLineType": "Sale",
      "receiptLineName": "Product B",
      "receiptLineQuantity": 2.0,
      "receiptLinePrice": 25.0,
      "receiptLineTotal": 50.0,
      "taxCode": "C",
      "taxPercent": 5.0,
      "taxID": 3
    },
    {
      "receiptLineNo": 3,
      "receiptLineType": "Sale",
      "receiptLineName": "Product C",
      "receiptLineQuantity": 1.0,
      "receiptLinePrice": 20.0,
      "receiptLineTotal": 20.0,
      "taxCode": "B",
      "taxPercent": 0.0,
      "taxID": 2
    }
  ],
  "receiptTaxes": [
    {
      "taxCode": "A",
      "taxPercent": 15.0,
      "taxID": 1,
      "taxAmount": 15.0,
      "salesAmountWithTax": 115.0
    },
    {
      "taxCode": "C",
      "taxPercent": 5.0,
      "taxID": 3,
      "taxAmount": 2.5,
      "salesAmountWithTax": 52.5
    },
    {
      "taxCode": "B",
      "taxPercent": 0.0,
      "taxID": 2,
      "taxAmount": 0.0,
      "salesAmountWithTax": 20.0
    }
  ],
  "receiptTotal": 187.5,
  "receiptPayments": [
    {
      "moneyTypeCode": "Cash",
      "paymentAmount": 100.0
    },
    {
      "moneyTypeCode": "Card",
      "paymentAmount": 87.5
    }
  ]
}
```

---

## Validation Rules

### 1. Receipt Total Validation
```
receiptTotal === SUM(receiptTaxes[].salesAmountWithTax)
```

### 2. Payment Validation
```
SUM(receiptPayments[].paymentAmount) === receiptTotal
```

### 3. Line Total Validation
```
receiptLineTotal === receiptLinePrice × receiptLineQuantity
```

### 4. Tax Grouping
- Group by `taxID` (not just `taxCode`)
- Sum `taxAmount` and `salesAmountWithTax` per group

---

## BCMath Calculation Formula

### Tax-Exclusive (receiptLinesTaxInclusive = true)
```php
$lineNet = bcmul($price, $quantity, 6);
$taxAmount = bcdiv(bcmul($lineNet, $taxPercent, 6), '100', 6);
$salesAmountWithTax = bcadd($lineNet, $taxAmount, 6);
```

### Tax-Inclusive (receiptLinesTaxInclusive = false)
```php
$lineGross = bcmul($price, $quantity, 6);
$salesAmountWithTax = $lineGross;
$divisor = bcadd('100', $taxPercent, 6);
$taxAmount = bcdiv(bcmul($lineGross, $taxPercent, 6), $divisor, 6);
```

### Rounding
- Use scale 6 for internal calculations
- Round to 2 decimals only at the end
- Cast to `(float)` for JSON encoding

---

## Common Mistakes

| Mistake | Consequence | Fix |
|---------|-------------|-----|
| Using string totals in JSON | RCPT025 signature error | Cast to `(float)` |
| Wrong tax calculation for `taxInclusive=true` | RCPT020 total mismatch | Tax added on top, not extracted |
| Hardcoded `taxID` | RCPT014 invalid tax ID | Use `taxID` from FDMS `applicableTaxes` |
| Cached fiscal day number | RCPT021 invalid fiscal day | Fetch from FDMS `GetStatus` |
| Rounding too early | RCPT020 precision errors | Use BCMath scale 6, round at end |
