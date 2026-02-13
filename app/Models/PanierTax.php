<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanierTax extends Model
{
    public const ZIMRA_TAX_TYPES = [
        3 => [
            'zimra_tax_id' => 3,
            'name' => 'Exempt',
            'percentage' => 0,
            'code' => 'E',
        ],
        515 => [
            'zimra_tax_id' => 515,
            'name' => 'Standard rated',
            'percentage' => 15.5,
            'code' => 'A',
        ],
        514 => [
            'zimra_tax_id' => 514,
            'name' => 'Non-VAT Withholding Tax',
            'percentage' => 5,
            'code' => 'W',
        ],
        2 => [
            'zimra_tax_id' => 2,
            'name' => 'Zero rate',
            'percentage' => 0,
            'code' => 'Z',
        ],
    ];

    protected $fillable = [
        'panier_id',
        'name',
        'percentage',
        'code',
        'zimra_tax_id',
        'panier_data',
    ];

    protected $casts = [
        'percentage' => 'decimal:4',
        'zimra_tax_id' => 'integer',
        'panier_data' => 'array',
    ];

    public function products()
    {
        return $this->hasMany(PanierProduct::class, 'applicable_tax_id', 'panier_id');
    }
}
