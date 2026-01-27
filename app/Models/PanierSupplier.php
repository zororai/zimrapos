<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanierSupplier extends Model
{
    protected $fillable = [
        'panier_id',
        'name',
        'email',
        'phone',
        'address',
        'panier_data',
    ];

    protected $casts = [
        'panier_data' => 'array',
    ];
}
