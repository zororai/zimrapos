<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Company Model
 * 
 * Represents a company with its own ZIMRA FDMS device.
 * Each company has exactly one device_id for strict fiscal data isolation.
 */
class Company extends Model
{
    protected $fillable = [
        'name',
        'tin',
        'device_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'device_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get all devices for this company
     */
    public function devices(): HasMany
    {
        return $this->hasMany(CompanyDevice::class);
    }

    /**
     * Get the active device for this company
     */
    public function activeDevice(): ?CompanyDevice
    {
        return $this->devices()->where('is_active', true)->first();
    }

    /**
     * Get the active device ID for this company
     */
    public function getActiveDeviceId(): ?int
    {
        return $this->activeDevice()?->device_id;
    }

    /**
     * Get all receipts for this company's devices
     */
    public function receipts(): HasMany
    {
        $deviceIds = $this->devices()->pluck('device_id');
        return $this->hasMany(Receipt::class, 'device_id')->whereIn('device_id', $deviceIds);
    }

    /**
     * Get all fiscal days for this company's devices
     */
    public function fiscalDays(): HasMany
    {
        $deviceIds = $this->devices()->pluck('device_id');
        return $this->hasMany(FiscalDay::class, 'device_id')->whereIn('device_id', $deviceIds);
    }

    /**
     * Get active company
     */
    public static function getActive(): ?self
    {
        return self::where('is_active', true)->first();
    }

    /**
     * Get company by device ID
     */
    public static function findByDeviceId(int $deviceId): ?self
    {
        $companyDevice = CompanyDevice::where('device_id', $deviceId)->first();
        return $companyDevice ? $companyDevice->company : null;
    }

    /**
     * Verify this company owns the given device ID
     */
    public function ownsDevice(int $deviceId): bool
    {
        return CompanyDevice::deviceBelongsToCompany($deviceId, $this->id);
    }
}
