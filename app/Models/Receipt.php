<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
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
        'receipt_hash',
        'receipt_signature',
        'receipt_qr_code',
        'verification_code',
        'zimra_response',
        'receipt_date',
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
        'receipt_signature' => 'array',
        'zimra_response' => 'array',
        'validation_errors' => 'array',
        'receipt_date' => 'datetime',
        'receipt_total' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'is_valid' => 'boolean',
        'has_red_errors' => 'boolean',
        'has_gray_errors' => 'boolean',
    ];
}
