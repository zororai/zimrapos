<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZimraConfig extends Model
{
    protected $fillable = [
        'base_url',
        'device_model',
        'device_version',
        'device_id',
        'serial_number',
        'activation_key',
        'private_key',
        'certificate',
        'is_active',
        'reporting_frequency',
        'qr_url',
        'taxes',
        'device_operating_mode',
        'certificate_valid_till',
        'fiscal_day_status',
        'last_receipt_global_no',
        'last_fiscal_day_no',
    ];

    protected $hidden = [
        'private_key',
        'activation_key',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'device_id' => 'integer',
            'reporting_frequency' => 'integer',
            'taxes' => 'array',
            'certificate_valid_till' => 'datetime',
        ];
    }

    /**
     * Get the active ZIMRA configuration.
     */
    public static function getActive(): ?self
    {
        return self::where('is_active', true)->first();
    }
}
