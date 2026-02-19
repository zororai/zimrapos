<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalDay extends Model
{
    protected $fillable = [
        'fiscal_day_no',
        'device_id',
        'status',
        'opened_at',
        'closed_at',
        'open_operation_id',
        'close_operation_id',
        'receipt_counter',
        'fiscal_counters',
        'close_response',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'fiscal_counters' => 'array',
            'close_response' => 'array',
            'fiscal_day_no' => 'integer',
            'device_id' => 'integer',
            'receipt_counter' => 'integer',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public static function getCurrentOpen(int $deviceId): ?self
    {
        return self::where('device_id', $deviceId)
            ->where('status', 'open')
            ->first();
    }

    public static function getNextFiscalDayNo(int $deviceId): int
    {
        $lastDay = self::where('device_id', $deviceId)
            ->orderBy('fiscal_day_no', 'desc')
            ->first();

        return $lastDay ? $lastDay->fiscal_day_no + 1 : 1;
    }
}
