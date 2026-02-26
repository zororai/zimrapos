<?php

namespace App\DTOs;

/**
 * ZIMRA Fiscal Device Gateway API v7.2 - Fiscal Counter DTO
 * 
 * Represents a single fiscal counter in the CloseDay payload.
 * Field names and types must match ZIMRA v7.2 specification exactly.
 */
class FiscalCounterDTO
{
    public string $fiscalCounterType;
    public string $fiscalCounterCurrency;
    public float $fiscalCounterTaxPercent;
    public int $fiscalCounterTaxID;
    public float $fiscalCounterValue;

    public function __construct(
        string $fiscalCounterType,
        string $fiscalCounterCurrency,
        float $fiscalCounterTaxPercent,
        int $fiscalCounterTaxID,
        float $fiscalCounterValue
    ) {
        $this->fiscalCounterType = $fiscalCounterType;
        $this->fiscalCounterCurrency = $fiscalCounterCurrency;
        $this->fiscalCounterTaxPercent = $fiscalCounterTaxPercent;
        $this->fiscalCounterTaxID = $fiscalCounterTaxID;
        $this->fiscalCounterValue = $fiscalCounterValue;
    }

    public function toArray(): array
    {
        return [
            'fiscalCounterType' => $this->fiscalCounterType,
            'fiscalCounterCurrency' => $this->fiscalCounterCurrency,
            'fiscalCounterTaxPercent' => $this->fiscalCounterTaxPercent,
            'fiscalCounterTaxID' => $this->fiscalCounterTaxID,
            'fiscalCounterValue' => $this->fiscalCounterValue,
        ];
    }
}
