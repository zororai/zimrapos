<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanierDebitNote extends Model
{
    protected $fillable = [
        'panier_id',
        'invoice_id',
        'customer_id',
        'total',
        'reason',
        'zimra_fiscalized',
        'zimra_fiscal_code',
        'products',
        'panier_data',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'zimra_fiscalized' => 'boolean',
        'products' => 'array',
        'panier_data' => 'array',
    ];

    public function invoice()
    {
        return $this->belongsTo(PanierInvoice::class, 'invoice_id', 'panier_id');
    }

    public function customer()
    {
        return $this->belongsTo(PanierCustomer::class, 'customer_id', 'panier_id');
    }
}
