<?php

namespace App\Services;

use App\Models\PanierProduct;
use App\Models\PanierCustomer;
use App\Models\PanierSupplier;
use App\Models\PanierTax;
use App\Models\PanierCurrency;
use App\Models\PanierSale;
use App\Models\PanierInvoice;
use App\Models\PanierDebitNote;
use App\Models\PanierCreditNote;
use App\Models\PanierDeliveryNote;
use App\Models\PanierQuotation;

class PanierSyncService
{
    public function syncProducts(array $apiResponse): void
    {
        $created = $apiResponse['created'] ?? [];
        foreach ($created as $product) {
            PanierProduct::updateOrCreate(
                ['panier_id' => $product['id'] ?? $product['_id'] ?? null],
                [
                    'name' => $product['name'] ?? '',
                    'description' => $product['description'] ?? null,
                    'buying_price' => $product['buying_price'] ?? null,
                    'selling_price' => $product['selling_price'] ?? 0,
                    'quantity' => $product['quantity'] ?? 0,
                    'hs_code' => $product['hs_code'] ?? null,
                    'sku' => $product['sku'] ?? null,
                    'is_inventory_item' => $product['is_inventory_item'] ?? true,
                    'applicable_tax_id' => $product['applicable_tax_id'] ?? null,
                    'suppliers' => $product['suppliers'] ?? null,
                    'panier_data' => $product,
                ]
            );
        }
    }

    public function syncProductsFromSearch(array $apiResponse): void
    {
        $searched = $apiResponse['searched'] ?? [];
        foreach ($searched as $product) {
            PanierProduct::updateOrCreate(
                ['panier_id' => $product['id'] ?? $product['_id'] ?? null],
                [
                    'name' => $product['name'] ?? '',
                    'description' => $product['description'] ?? null,
                    'buying_price' => $product['buying_price'] ?? null,
                    'selling_price' => $product['selling_price'] ?? 0,
                    'quantity' => $product['quantity'] ?? 0,
                    'hs_code' => $product['hs_code'] ?? null,
                    'sku' => $product['sku'] ?? null,
                    'is_inventory_item' => $product['is_inventory_item'] ?? true,
                    'applicable_tax_id' => $product['applicable_tax_id'] ?? null,
                    'suppliers' => $product['suppliers'] ?? null,
                    'panier_data' => $product,
                ]
            );
        }
    }

    public function deleteProducts(array $ids): void
    {
        PanierProduct::whereIn('panier_id', $ids)->delete();
    }

    public function syncCustomers(array $apiResponse): void
    {
        $created = $apiResponse['created'] ?? [];
        foreach ($created as $customer) {
            PanierCustomer::updateOrCreate(
                ['panier_id' => $customer['id'] ?? $customer['_id'] ?? null],
                [
                    'name' => $customer['name'] ?? '',
                    'email' => $customer['email'] ?? null,
                    'phone' => $customer['phone'] ?? null,
                    'address' => $customer['address'] ?? null,
                    'tax_id' => $customer['tax_id'] ?? null,
                    'panier_data' => $customer,
                ]
            );
        }
    }

    public function syncCustomersFromSearch(array $apiResponse): void
    {
        $searched = $apiResponse['searched'] ?? [];
        foreach ($searched as $customer) {
            PanierCustomer::updateOrCreate(
                ['panier_id' => $customer['id'] ?? $customer['_id'] ?? null],
                [
                    'name' => $customer['name'] ?? '',
                    'email' => $customer['email'] ?? null,
                    'phone' => $customer['phone'] ?? null,
                    'address' => $customer['address'] ?? null,
                    'tax_id' => $customer['tax_id'] ?? null,
                    'panier_data' => $customer,
                ]
            );
        }
    }

    public function deleteCustomers(array $ids): void
    {
        PanierCustomer::whereIn('panier_id', $ids)->delete();
    }

    public function syncSuppliers(array $apiResponse): void
    {
        $created = $apiResponse['created'] ?? [];
        foreach ($created as $supplier) {
            PanierSupplier::updateOrCreate(
                ['panier_id' => $supplier['id'] ?? $supplier['_id'] ?? null],
                [
                    'name' => $supplier['name'] ?? '',
                    'email' => $supplier['email'] ?? null,
                    'phone' => $supplier['phone'] ?? null,
                    'address' => $supplier['address'] ?? null,
                    'panier_data' => $supplier,
                ]
            );
        }
    }

    public function syncSuppliersFromSearch(array $apiResponse): void
    {
        $searched = $apiResponse['searched'] ?? [];
        foreach ($searched as $supplier) {
            PanierSupplier::updateOrCreate(
                ['panier_id' => $supplier['id'] ?? $supplier['_id'] ?? null],
                [
                    'name' => $supplier['name'] ?? '',
                    'email' => $supplier['email'] ?? null,
                    'phone' => $supplier['phone'] ?? null,
                    'address' => $supplier['address'] ?? null,
                    'panier_data' => $supplier,
                ]
            );
        }
    }

    public function deleteSuppliers(array $ids): void
    {
        PanierSupplier::whereIn('panier_id', $ids)->delete();
    }

    public function syncTaxes(array $apiResponse): void
    {
        $created = $apiResponse['created'] ?? [];
        foreach ($created as $tax) {
            PanierTax::updateOrCreate(
                ['panier_id' => $tax['id'] ?? $tax['_id'] ?? null],
                [
                    'name' => $tax['name'] ?? '',
                    'percentage' => $tax['percentage'] ?? 0,
                    'code' => $tax['code'] ?? null,
                    'panier_data' => $tax,
                ]
            );
        }
    }

    public function syncTaxesFromSearch(array $apiResponse): void
    {
        $searched = $apiResponse['searched'] ?? [];
        foreach ($searched as $tax) {
            PanierTax::updateOrCreate(
                ['panier_id' => $tax['id'] ?? $tax['_id'] ?? null],
                [
                    'name' => $tax['name'] ?? '',
                    'percentage' => $tax['percentage'] ?? 0,
                    'code' => $tax['code'] ?? null,
                    'panier_data' => $tax,
                ]
            );
        }
    }

    public function deleteTaxes(array $ids): void
    {
        PanierTax::whereIn('panier_id', $ids)->delete();
    }

    public function syncCurrencies(array $apiResponse): void
    {
        $created = $apiResponse['created'] ?? [];
        foreach ($created as $currency) {
            PanierCurrency::updateOrCreate(
                ['panier_id' => $currency['id'] ?? $currency['_id'] ?? null],
                [
                    'code' => $currency['code'] ?? '',
                    'name' => $currency['name'] ?? null,
                    'exchange_rate' => $currency['exchange_rate'] ?? 1,
                    'panier_data' => $currency,
                ]
            );
        }
    }

    public function syncCurrenciesFromSearch(array $apiResponse): void
    {
        $searched = $apiResponse['searched'] ?? [];
        foreach ($searched as $currency) {
            PanierCurrency::updateOrCreate(
                ['panier_id' => $currency['id'] ?? $currency['_id'] ?? null],
                [
                    'code' => $currency['code'] ?? '',
                    'name' => $currency['name'] ?? null,
                    'exchange_rate' => $currency['exchange_rate'] ?? 1,
                    'panier_data' => $currency,
                ]
            );
        }
    }

    public function deleteCurrencies(array $ids): void
    {
        PanierCurrency::whereIn('panier_id', $ids)->delete();
    }

    public function syncSale(array $apiResponse): void
    {
        $sale = $apiResponse['created'] ?? $apiResponse;
        if (isset($sale['id']) || isset($sale['_id'])) {
            PanierSale::updateOrCreate(
                ['panier_id' => $sale['id'] ?? $sale['_id']],
                [
                    'customer_id' => $sale['customer_id'] ?? null,
                    'currency_id' => $sale['currency_id'] ?? null,
                    'total' => $sale['total'] ?? 0,
                    'tax_total' => $sale['tax_total'] ?? 0,
                    'payment_method' => $sale['payment_method'] ?? null,
                    'payment_status' => $sale['payment_status'] ?? null,
                    'zimra_fiscalized' => $sale['zimra_fiscalized'] ?? false,
                    'zimra_fiscal_code' => $sale['zimra_fiscal_code'] ?? null,
                    'products' => $sale['products'] ?? null,
                    'panier_data' => $sale,
                    'sale_date' => $sale['created_at'] ?? now(),
                ]
            );
        }
    }

    public function syncSalesFromSearch(array $apiResponse): void
    {
        $searched = $apiResponse['searched'] ?? [];
        foreach ($searched as $sale) {
            PanierSale::updateOrCreate(
                ['panier_id' => $sale['id'] ?? $sale['_id'] ?? null],
                [
                    'customer_id' => $sale['customer_id'] ?? null,
                    'currency_id' => $sale['currency_id'] ?? null,
                    'total' => $sale['total'] ?? 0,
                    'tax_total' => $sale['tax_total'] ?? 0,
                    'payment_method' => $sale['payment_method'] ?? null,
                    'payment_status' => $sale['payment_status'] ?? null,
                    'zimra_fiscalized' => $sale['zimra_fiscalized'] ?? false,
                    'zimra_fiscal_code' => $sale['zimra_fiscal_code'] ?? null,
                    'products' => $sale['products'] ?? null,
                    'panier_data' => $sale,
                    'sale_date' => $sale['created_at'] ?? now(),
                ]
            );
        }
    }

    public function voidSale(string $id, string $reason): void
    {
        PanierSale::where('panier_id', $id)->update([
            'is_voided' => true,
            'void_reason' => $reason,
        ]);
    }

    public function syncInvoices(array $apiResponse): void
    {
        $created = $apiResponse['created'] ?? [];
        foreach ($created as $invoice) {
            PanierInvoice::updateOrCreate(
                ['panier_id' => $invoice['id'] ?? $invoice['_id'] ?? null],
                [
                    'invoice_number' => $invoice['invoice_number'] ?? null,
                    'customer_id' => $invoice['customer_id'] ?? null,
                    'currency_id' => $invoice['currency_id'] ?? null,
                    'total' => $invoice['total'] ?? 0,
                    'tax_total' => $invoice['tax_total'] ?? 0,
                    'status' => $invoice['status'] ?? null,
                    'zimra_fiscalized' => $invoice['zimra_fiscalized'] ?? false,
                    'zimra_fiscal_code' => $invoice['zimra_fiscal_code'] ?? null,
                    'products' => $invoice['products'] ?? null,
                    'panier_data' => $invoice,
                    'due_date' => $invoice['due_date'] ?? null,
                    'invoice_date' => $invoice['created_at'] ?? now(),
                ]
            );
        }
    }

    public function syncInvoicesFromSearch(array $apiResponse): void
    {
        $searched = $apiResponse['searched'] ?? [];
        foreach ($searched as $invoice) {
            PanierInvoice::updateOrCreate(
                ['panier_id' => $invoice['id'] ?? $invoice['_id'] ?? null],
                [
                    'invoice_number' => $invoice['invoice_number'] ?? null,
                    'customer_id' => $invoice['customer_id'] ?? null,
                    'currency_id' => $invoice['currency_id'] ?? null,
                    'total' => $invoice['total'] ?? 0,
                    'tax_total' => $invoice['tax_total'] ?? 0,
                    'status' => $invoice['status'] ?? null,
                    'zimra_fiscalized' => $invoice['zimra_fiscalized'] ?? false,
                    'zimra_fiscal_code' => $invoice['zimra_fiscal_code'] ?? null,
                    'products' => $invoice['products'] ?? null,
                    'panier_data' => $invoice,
                    'due_date' => $invoice['due_date'] ?? null,
                    'invoice_date' => $invoice['created_at'] ?? now(),
                ]
            );
        }
    }

    public function deleteInvoices(array $ids): void
    {
        PanierInvoice::whereIn('panier_id', $ids)->delete();
    }

    public function syncDebitNotes(array $apiResponse): void
    {
        $created = $apiResponse['created'] ?? [];
        foreach ($created as $note) {
            PanierDebitNote::updateOrCreate(
                ['panier_id' => $note['id'] ?? $note['_id'] ?? null],
                [
                    'invoice_id' => $note['invoice_id'] ?? null,
                    'customer_id' => $note['customer_id'] ?? null,
                    'total' => $note['total'] ?? 0,
                    'reason' => $note['reason'] ?? null,
                    'zimra_fiscalized' => $note['zimra_fiscalized'] ?? false,
                    'zimra_fiscal_code' => $note['zimra_fiscal_code'] ?? null,
                    'products' => $note['products'] ?? null,
                    'panier_data' => $note,
                ]
            );
        }
    }

    public function syncDebitNotesFromSearch(array $apiResponse): void
    {
        $searched = $apiResponse['searched'] ?? [];
        foreach ($searched as $note) {
            PanierDebitNote::updateOrCreate(
                ['panier_id' => $note['id'] ?? $note['_id'] ?? null],
                [
                    'invoice_id' => $note['invoice_id'] ?? null,
                    'customer_id' => $note['customer_id'] ?? null,
                    'total' => $note['total'] ?? 0,
                    'reason' => $note['reason'] ?? null,
                    'zimra_fiscalized' => $note['zimra_fiscalized'] ?? false,
                    'zimra_fiscal_code' => $note['zimra_fiscal_code'] ?? null,
                    'products' => $note['products'] ?? null,
                    'panier_data' => $note,
                ]
            );
        }
    }

    public function deleteDebitNotes(array $ids): void
    {
        PanierDebitNote::whereIn('panier_id', $ids)->delete();
    }

    public function syncCreditNotes(array $apiResponse): void
    {
        $created = $apiResponse['created'] ?? [];
        foreach ($created as $note) {
            PanierCreditNote::updateOrCreate(
                ['panier_id' => $note['id'] ?? $note['_id'] ?? null],
                [
                    'sale_id' => $note['sale_id'] ?? null,
                    'customer_id' => $note['customer_id'] ?? null,
                    'total' => $note['total'] ?? 0,
                    'reason' => $note['reason'] ?? null,
                    'zimra_fiscalized' => $note['zimra_fiscalized'] ?? false,
                    'zimra_fiscal_code' => $note['zimra_fiscal_code'] ?? null,
                    'products' => $note['products'] ?? null,
                    'panier_data' => $note,
                ]
            );
        }
    }

    public function syncCreditNotesFromSearch(array $apiResponse): void
    {
        $searched = $apiResponse['searched'] ?? [];
        foreach ($searched as $note) {
            PanierCreditNote::updateOrCreate(
                ['panier_id' => $note['id'] ?? $note['_id'] ?? null],
                [
                    'sale_id' => $note['sale_id'] ?? null,
                    'customer_id' => $note['customer_id'] ?? null,
                    'total' => $note['total'] ?? 0,
                    'reason' => $note['reason'] ?? null,
                    'zimra_fiscalized' => $note['zimra_fiscalized'] ?? false,
                    'zimra_fiscal_code' => $note['zimra_fiscal_code'] ?? null,
                    'products' => $note['products'] ?? null,
                    'panier_data' => $note,
                ]
            );
        }
    }

    public function deleteCreditNotes(array $ids): void
    {
        PanierCreditNote::whereIn('panier_id', $ids)->delete();
    }

    public function syncDeliveryNotes(array $apiResponse): void
    {
        $created = $apiResponse['created'] ?? [];
        foreach ($created as $note) {
            PanierDeliveryNote::updateOrCreate(
                ['panier_id' => $note['id'] ?? $note['_id'] ?? null],
                [
                    'customer_id' => $note['customer_id'] ?? null,
                    'delivery_address' => $note['delivery_address'] ?? null,
                    'recipients' => $note['recipients'] ?? null,
                    'products' => $note['products'] ?? null,
                    'panier_data' => $note,
                    'delivery_date' => $note['delivery_date'] ?? null,
                ]
            );
        }
    }

    public function syncDeliveryNotesFromSearch(array $apiResponse): void
    {
        $searched = $apiResponse['searched'] ?? [];
        foreach ($searched as $note) {
            PanierDeliveryNote::updateOrCreate(
                ['panier_id' => $note['id'] ?? $note['_id'] ?? null],
                [
                    'customer_id' => $note['customer_id'] ?? null,
                    'delivery_address' => $note['delivery_address'] ?? null,
                    'recipients' => $note['recipients'] ?? null,
                    'products' => $note['products'] ?? null,
                    'panier_data' => $note,
                    'delivery_date' => $note['delivery_date'] ?? null,
                ]
            );
        }
    }

    public function deleteDeliveryNotes(array $ids): void
    {
        PanierDeliveryNote::whereIn('panier_id', $ids)->delete();
    }

    public function syncQuotations(array $apiResponse): void
    {
        $created = $apiResponse['created'] ?? [];
        foreach ($created as $quotation) {
            PanierQuotation::updateOrCreate(
                ['panier_id' => $quotation['id'] ?? $quotation['_id'] ?? null],
                [
                    'customer_id' => $quotation['customer_id'] ?? null,
                    'currency_id' => $quotation['currency_id'] ?? null,
                    'total' => $quotation['total'] ?? 0,
                    'status' => $quotation['status'] ?? null,
                    'valid_until' => $quotation['valid_until'] ?? null,
                    'recipients' => $quotation['recipients'] ?? null,
                    'products' => $quotation['products'] ?? null,
                    'panier_data' => $quotation,
                ]
            );
        }
    }

    public function syncQuotationsFromSearch(array $apiResponse): void
    {
        $searched = $apiResponse['searched'] ?? [];
        foreach ($searched as $quotation) {
            PanierQuotation::updateOrCreate(
                ['panier_id' => $quotation['id'] ?? $quotation['_id'] ?? null],
                [
                    'customer_id' => $quotation['customer_id'] ?? null,
                    'currency_id' => $quotation['currency_id'] ?? null,
                    'total' => $quotation['total'] ?? 0,
                    'status' => $quotation['status'] ?? null,
                    'valid_until' => $quotation['valid_until'] ?? null,
                    'recipients' => $quotation['recipients'] ?? null,
                    'products' => $quotation['products'] ?? null,
                    'panier_data' => $quotation,
                ]
            );
        }
    }

    public function deleteQuotations(array $ids): void
    {
        PanierQuotation::whereIn('panier_id', $ids)->delete();
    }
}
