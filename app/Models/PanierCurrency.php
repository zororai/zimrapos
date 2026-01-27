<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanierCurrency extends Model
{
    protected $fillable = [
        'panier_id',
        'code',
        'name',
        'exchange_rate',
        'panier_data',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:10',
        'panier_data' => 'array',
    ];
}
