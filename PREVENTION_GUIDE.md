# ZIMRA Data Integrity Prevention Guide

## Quick Summary

To prevent `CountersMismatch` errors in the future:

### 1. Apply Database Constraints
```bash
php artisan migrate
```
Files created:
- `database/migrations/2026_02_25_074100_add_receipt_integrity_constraints.php`
- `database/migrations/2026_02_25_074200_create_audit_logs_table.php`

### 2. Model Protection Added
- `app/Models/Receipt.php` - Now prevents modification/deletion of receipts in closed fiscal days

### 3. Backup Before Closing
```bash
php artisan zimra:backup
```
- File: `app/Console/Commands/BackupZimraData.php`
- Backups stored in: `storage/app/backups/`

### 4. Best Practices

**DO:**
- ✅ Backup before closing fiscal days
- ✅ Use soft deletes only
- ✅ Let system auto-generate counters
- ✅ Fix validation errors before closing

**DON'T:**
- ❌ Modify receipts in closed fiscal days
- ❌ Manually change `receipt_counter` or `receipt_global_no`
- ❌ Use `forceDelete()` on receipts
- ❌ Bulk update `is_valid` without understanding impact

### 5. If Corruption Occurs

1. Stop operations
2. Contact ZIMRA: fdms@zimra.co.zw / +263 4 758891-5
3. Provide: Device ID, error logs, backup file
