<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanierCreditNote extends Model
{
    protected $fillable = [
        'panier_id',
        'sale_id',
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

    public function sale()
    {
        return $this->belongsTo(PanierSale::class, 'sale_id', 'panier_id');
    }

    public function customer()
    {
        return $this->belongsTo(PanierCustomer::class, 'customer_id', 'panier_id');
    }
}
