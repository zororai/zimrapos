<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalizationRequest extends Model
{
    protected $fillable = [
        'document_id',
        'document_type',
        'request_data',
        'status',
        'response_data',
        'error_message',
        'attempts',
        'processed_at',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'processed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'attempts' => $this->attempts + 1,
        ]);
    }

    public function markAsSuccess(array $responseData): void
    {
        $this->update([
            'status' => self::STATUS_SUCCESS,
            'response_data' => $responseData,
            'processed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'processed_at' => now(),
        ]);
    }
}
