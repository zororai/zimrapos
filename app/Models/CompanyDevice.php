<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CompanyDevice Model
 * 
 * Represents the mapping between a company and its ZIMRA FDMS device(s).
 * One company can have multiple devices, but only one can be active at a time.
 * One device belongs to exactly one company.
 */
class CompanyDevice extends Model
{
    protected $fillable = [
        'company_id',
        'device_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'device_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the company that owns this device
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the active device for a company
     */
    public static function getActiveForCompany(int $companyId): ?self
    {
        return self::where('company_id', $companyId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get company ID that owns a device
     */
    public static function getCompanyIdForDevice(int $deviceId): ?int
    {
        $mapping = self::where('device_id', $deviceId)->first();
        return $mapping?->company_id;
    }

    /**
     * Verify device belongs to company
     */
    public static function deviceBelongsToCompany(int $deviceId, int $companyId): bool
    {
        return self::where('device_id', $deviceId)
            ->where('company_id', $companyId)
            ->exists();
    }
}
