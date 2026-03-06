# Company Contact Fields Added to ZIMRA Configuration

## Overview
Added company address, email, and phone fields to ZIMRA configuration so they can be displayed on invoices, debit notes, and credit notes.

## Database Changes

### Migration Created
`2026_03_06_065300_add_company_contact_fields_to_zimra_configs_table.php`

**New Fields:**
- `company_address` (text, nullable) - Company physical address
- `company_email` (string, nullable) - Company email address
- `company_phone` (string, nullable) - Company phone number

### Migration Applied
```bash
php artisan migrate
```

## Model Updates

### ZimraConfig Model
Added to `$fillable` array:
- `company_address`
- `company_email`
- `company_phone`

## API Updates

### ZimraController::updateConfig()
Added validation for new fields:
```php
'company_name' => 'sometimes|string|max:255',
'company_tin' => 'sometimes|string|max:50',
'company_address' => 'sometimes|string|nullable',
'company_email' => 'sometimes|email|nullable',
'company_phone' => 'sometimes|string|max:50|nullable',
```

## PDF Template Updates

### Regular Receipt PDF (`receipts/pdf.blade.php`)
Updated SELLER section to display:
- Company Name
- TIN
- **Address** (if set)
- **Phone** (if set)
- **Email** (if set)
- Device ID

### Debit Note PDF (`receipts/debit_note_pdf.blade.php`)
Updated SELLER section with same fields as regular receipt.

## How to Use

### 1. Update Configuration via API

**Endpoint:** `PUT /api/zimra/config/{id}`

**Request Body:**
```json
{
  "company_name": "My Company Ltd",
  "company_tin": "1234567890",
  "company_address": "123 Main Street, Harare, Zimbabwe",
  "company_email": "info@mycompany.co.zw",
  "company_phone": "+263 4 123 4567",
  "base_url": "https://fdmsapitest.zimra.co.zw",
  "device_model": "Server",
  "device_version": "v1"
}
```

### 2. PDF Generation

When generating PDFs for receipts, debit notes, or credit notes, the system will automatically:
1. Fetch the active ZIMRA configuration
2. Extract company contact details
3. Display them in the SELLER section

**Example Output:**
```
SELLER
My Company Ltd
TIN: 1234567890
123 Main Street, Harare, Zimbabwe
Tel: +263 4 123 4567
Email: info@mycompany.co.zw
Device ID: 32558
```

## Frontend Integration

To update the configuration form, add these fields:

```html
<input type="text" name="company_name" placeholder="Company Name *" required>
<input type="text" name="company_tin" placeholder="Company TIN *" required>
<textarea name="company_address" placeholder="Company Address"></textarea>
<input type="email" name="company_email" placeholder="Company Email">
<input type="tel" name="company_phone" placeholder="Company Phone">
<input type="url" name="base_url" placeholder="Base URL *" required>
<input type="text" name="device_model" placeholder="Device Model" value="Server">
<input type="text" name="device_version" placeholder="Device Version" value="v1">
```

## Benefits

1. **Professional Invoices** - All company contact information displayed
2. **Consistency** - Same company details on all receipts, debit notes, credit notes
3. **Easy Updates** - Change company details in one place
4. **Multi-Company Support** - Each ZIMRA config can have different company details

## Testing

1. Update ZIMRA configuration with company contact details
2. Create a new receipt/invoice
3. Download PDF and verify SELLER section shows all details
4. Create a debit note
5. Download PDF and verify SELLER section shows all details

## Files Modified

1. `database/migrations/2026_03_06_065300_add_company_contact_fields_to_zimra_configs_table.php` - New
2. `app/Models/ZimraConfig.php` - Updated fillable array
3. `app/Http/Controllers/ZimraController.php` - Updated validation
4. `resources/views/receipts/pdf.blade.php` - Updated SELLER section
5. `resources/views/receipts/debit_note_pdf.blade.php` - Updated SELLER section
