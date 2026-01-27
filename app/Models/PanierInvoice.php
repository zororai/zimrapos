<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanierInvoice extends Model
{
    protected $fillable = [
        'panier_id',
        'invoice_number',
        'customer_id',
        'currency_id',
        'total',
        'tax_total',
        'status',
        'zimra_fiscalized',
        'zimra_fiscal_code',
        'products',
        'panier_data',
        'due_date',
        'invoice_date',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'zimra_fiscalized' => 'boolean',
        'products' => 'array',
        'panier_data' => 'array',
        'due_date' => 'date',
        'invoice_date' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(PanierCustomer::class, 'customer_id', 'panier_id');
    }

    public function currency()
    {
        return $this->belongsTo(PanierCurrency::class, 'currency_id', 'panier_id');
    }

    public function debitNotes()
    {
        return $this->hasMany(PanierDebitNote::class, 'invoice_id', 'panier_id');
    }
}
