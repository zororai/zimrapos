<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'device_id',
        'invoice_no',
        'receipt_type',
        'receipt_currency',
        'receipt_counter',
        'receipt_global_no',
        'fiscal_day_no',
        'receipt_total',
        'tax_amount',
        'tax_code',
        'tax_percent',
        'payment_method',
        'receipt_lines',
        'receipt_taxes',
        'receipt_payments',
        'buyer_data',
        'receipt_hash',
        'receipt_signature',
        'receipt_qr_code',
        'qr_url',
        'verification_code',
        'zimra_response',
        'receipt_date',
        'date_issued',
        'payment_due',
        'validation_code',
        'validation_errors',
        'is_valid',
        'has_red_errors',
        'has_gray_errors',
        'fdms_receipt_id',
    ];

    protected $casts = [
        'receipt_lines' => 'array',
        'receipt_taxes' => 'array',
        'receipt_payments' => 'array',
        'buyer_data' => 'array',
        'receipt_signature' => 'array',
        'zimra_response' => 'array',
        'validation_errors' => 'array',
        'receipt_date' => 'datetime',
        'date_issued' => 'date',
        'payment_due' => 'date',
        'receipt_total' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'is_valid' => 'boolean',
        'has_red_errors' => 'boolean',
        'has_gray_errors' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (Receipt $receipt) {
            $fiscalDay = FiscalDay::where('device_id', $receipt->device_id)
                ->where('fiscal_day_no', $receipt->fiscal_day_no)
                ->first();

            if ($fiscalDay && $fiscalDay->status === 'closed') {
                throw new \Exception(
                    "Cannot modify receipt #{$receipt->receipt_global_no}: " .
                    "Fiscal day {$receipt->fiscal_day_no} is already closed"
                );
            }
        });

        static::deleting(function (Receipt $receipt) {
            $fiscalDay = FiscalDay::where('device_id', $receipt->device_id)
                ->where('fiscal_day_no', $receipt->fiscal_day_no)
                ->first();

            if ($fiscalDay && $fiscalDay->status === 'closed') {
                throw new \Exception(
                    "Cannot delete receipt #{$receipt->receipt_global_no}: " .
                    "Fiscal day {$receipt->fiscal_day_no} is already closed"
                );
            }
        });
    }

    public function fiscalDay()
    {
        return $this->belongsTo(FiscalDay::class, 'fiscal_day_no', 'fiscal_day_no');
    }
}
