<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanierTax extends Model
{
    protected $fillable = [
        'panier_id',
        'name',
        'percentage',
        'code',
        'panier_data',
    ];

    protected $casts = [
        'percentage' => 'decimal:4',
        'panier_data' => 'array',
    ];

    public function products()
    {
        return $this->hasMany(PanierProduct::class, 'applicable_tax_id', 'panier_id');
    }
}
