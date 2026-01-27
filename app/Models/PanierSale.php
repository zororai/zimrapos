<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanierSale extends Model
{
    protected $fillable = [
        'panier_id',
        'customer_id',
        'currency_id',
        'total',
        'tax_total',
        'payment_method',
        'payment_status',
        'is_voided',
        'void_reason',
        'zimra_fiscalized',
        'zimra_fiscal_code',
        'products',
        'panier_data',
        'sale_date',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'is_voided' => 'boolean',
        'zimra_fiscalized' => 'boolean',
        'products' => 'array',
        'panier_data' => 'array',
        'sale_date' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(PanierCustomer::class, 'customer_id', 'panier_id');
    }

    public function currency()
    {
        return $this->belongsTo(PanierCurrency::class, 'currency_id', 'panier_id');
    }

    public function creditNotes()
    {
        return $this->hasMany(PanierCreditNote::class, 'sale_id', 'panier_id');
    }
}
