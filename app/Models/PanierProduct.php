<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanierProduct extends Model
{
    protected $fillable = [
        'panier_id',
        'name',
        'description',
        'buying_price',
        'selling_price',
        'quantity',
        'hs_code',
        'sku',
        'is_inventory_item',
        'applicable_tax_id',
        'suppliers',
        'panier_data',
    ];

    protected $casts = [
        'buying_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_inventory_item' => 'boolean',
        'suppliers' => 'array',
        'panier_data' => 'array',
    ];

    public function tax()
    {
        return $this->belongsTo(PanierTax::class, 'applicable_tax_id', 'panier_id');
    }
}
