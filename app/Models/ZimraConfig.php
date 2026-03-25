<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ZimraConfig extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'company_tin',
        'company_address',
        'company_email',
        'company_phone',
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
            'is_active'              => 'boolean',
            'device_id'              => 'integer',
            'reporting_frequency'    => 'integer',
            'taxes'                  => 'array',
            'certificate_valid_till' => 'datetime',
        ];
    }

    /**
     * Global scope: every query is automatically scoped to the
     * authenticated user. New records also get user_id auto-assigned.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $query) {
            if (auth()->check()) {
                $query->where('zimra_configs.user_id', auth()->id());
            }
        });

        static::creating(function (self $config) {
            if (auth()->check() && empty($config->user_id)) {
                $config->user_id = auth()->id();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the active ZIMRA configuration for the current user.
     */
    public static function getActive(): ?self
    {
        return self::where('is_active', true)->first();
    }
}
