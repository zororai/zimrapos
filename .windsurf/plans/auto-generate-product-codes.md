# Auto-Generate Product Codes on PDF Generation

Instead of storing product codes in the database, automatically generate them on-the-fly when PDFs are rendered based on the product name or a hash/index.

## Approach

Rather than fixing the backend to preserve `productCode` through the FDMS submission pipeline, we can generate product codes dynamically in the PDF templates when they're rendered. This avoids modifying the complex FDMS validation logic.

## Generation Strategies

### Option 1: Name-Based Code (Recommended)
Extract a code from the product name using a simple algorithm:

**Examples:**
- "Leather Shoes" → `LEATH-001` (first 5 letters + line number)
- "Sports Shoes" → `SPORT-002`
- "Rubber Plastic" → `RUBBE-003`
- "Safety boots" → `SAFET-004`

**Pros:**
- Readable and somewhat meaningful
- No database changes needed
- Works immediately for all existing receipts

**Cons:**
- Not truly unique product codes
- Same product name gets different codes on different receipts

### Option 2: Hash-Based Code
Generate a short hash from the product name:

**Examples:**
- "Leather Shoes" → `LS-A3F2`
- "Sports Shoes" → `SS-B8D1`

**Pros:**
- Consistent for same product name
- Compact

**Cons:**
- Not human-readable
- Potential collisions

### Option 3: Simple Sequential
Just use formatted line numbers:

**Examples:**
- Line 1 → `ITEM-001`
- Line 2 → `ITEM-002`

**Pros:**
- Simple, guaranteed unique per receipt
- Clean appearance

**Cons:**
- No connection to actual product

## Implementation

### Files to Modify
- `resources/views/receipts/pdf.blade.php`
- `resources/views/receipts/credit_note_pdf.blade.php`
- `resources/views/receipts/debit_note_pdf.blade.php`

### Code Changes (Option 1 Example)

In each PDF template, replace:
```php
<td>{{ $line['productCode'] ?? ($index + 1) }}</td>
```

With:
```php
@php
    $productName = $line['receiptLineName'] ?? 'Item';
    // Extract first 5 letters, uppercase, remove spaces
    $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $productName), 0, 5));
    $generatedCode = $prefix . '-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);
@endphp
<td style="font-family: monospace; font-size: 8pt;">{{ $generatedCode }}</td>
```

**Result:**
- "Leather Shoes" (line 1) → `LEATH-001`
- "Sports Shoes" (line 2) → `SPORT-002`
- "Rubber Plastic" (line 3) → `RUBBE-003`

## Pros & Cons of This Approach

### Advantages
✅ No backend changes needed  
✅ Works for all existing receipts immediately  
✅ No database migration required  
✅ Doesn't interfere with FDMS submission  
✅ Simple to implement (3 file changes)

### Disadvantages
❌ Not "real" product codes from inventory system  
❌ Same product gets different codes on different receipts  
❌ Can't use codes for product tracking/analytics  
❌ Codes change if product name changes

## Alternative: Hybrid Approach

Keep the form field for manual entry, but auto-generate if empty:

```php
@php
    if (isset($line['productCode']) && $line['productCode']) {
        $displayCode = $line['productCode'];
    } else {
        // Auto-generate from product name
        $productName = $line['receiptLineName'] ?? 'Item';
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $productName), 0, 5));
        $displayCode = $prefix . '-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);
    }
@endphp
<td style="font-family: monospace; font-size: 8pt;">{{ $displayCode }}</td>
```

This way:
- If user enters a product code in the form → use it (if it survives backend processing)
- If no code available → auto-generate from product name

## Questions

1. **Which generation strategy do you prefer?**
   - Name-based (LEATH-001)
   - Hash-based (LS-A3F2)
   - Sequential (ITEM-001)
   - Hybrid (use manual if available, else auto-generate)

2. **Is this temporary or permanent?**
   - Temporary until we fix backend persistence?
   - Permanent solution (you don't need real product codes)?

3. **Do you have an actual product catalog/inventory system** that should be the source of truth for product codes?
