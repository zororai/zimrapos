<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanierDeliveryNote extends Model
{
    protected $fillable = [
        'panier_id',
        'customer_id',
        'delivery_address',
        'recipients',
        'products',
        'panier_data',
        'delivery_date',
    ];

    protected $casts = [
        'recipients' => 'array',
        'products' => 'array',
        'panier_data' => 'array',
        'delivery_date' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(PanierCustomer::class, 'customer_id', 'panier_id');
    }
}
