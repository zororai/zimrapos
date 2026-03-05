<?php

namespace App\Enums;

enum ReceiptType: string
{
    case FISCAL_INVOICE = 'FiscalInvoice';
    case CREDIT_NOTE = 'CreditNote';
    case DEBIT_NOTE = 'DebitNote';

    public function invoicePrefix(): string
    {
        return match($this) {
            self::FISCAL_INVOICE => 'INV',
            self::CREDIT_NOTE => 'CN',
            self::DEBIT_NOTE => 'DN',
        };
    }

    public function requiresOriginalReceipt(): bool
    {
        return match($this) {
            self::FISCAL_INVOICE => false,
            self::CREDIT_NOTE => true,
            self::DEBIT_NOTE => true,
        };
    }

    public function counterType(): string
    {
        return match($this) {
            self::FISCAL_INVOICE => 'SaleByTax',
            self::CREDIT_NOTE => 'CreditNoteByTax',
            self::DEBIT_NOTE => 'DebitNoteByTax',
        };
    }

    public function taxCounterType(): string
    {
        return match($this) {
            self::FISCAL_INVOICE => 'SaleTaxByTax',
            self::CREDIT_NOTE => 'CreditNoteTaxByTax',
            self::DEBIT_NOTE => 'DebitNoteTaxByTax',
        };
    }
}
