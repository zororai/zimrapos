<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanierQuotation extends Model
{
    protected $fillable = [
        'panier_id',
        'customer_id',
        'currency_id',
        'total',
        'status',
        'valid_until',
        'recipients',
        'products',
        'panier_data',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'valid_until' => 'date',
        'recipients' => 'array',
        'products' => 'array',
        'panier_data' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(PanierCustomer::class, 'customer_id', 'panier_id');
    }

    public function currency()
    {
        return $this->belongsTo(PanierCurrency::class, 'currency_id', 'panier_id');
    }
}
