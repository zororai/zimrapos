<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanierCustomer extends Model
{
    protected $fillable = [
        'panier_id',
        'name',
        'email',
        'phone',
        'address',
        'tax_id',
        'panier_data',
    ];

    protected $casts = [
        'panier_data' => 'array',
    ];

    public function sales()
    {
        return $this->hasMany(PanierSale::class, 'customer_id', 'panier_id');
    }

    public function invoices()
    {
        return $this->hasMany(PanierInvoice::class, 'customer_id', 'panier_id');
    }

    public function quotations()
    {
        return $this->hasMany(PanierQuotation::class, 'customer_id', 'panier_id');
    }
}
