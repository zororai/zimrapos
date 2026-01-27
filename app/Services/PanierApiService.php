<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\PendingRequest;

class PanierApiService
{
    protected string $baseUrl;
    protected string $appId;
    protected string $apiKey;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('panier.base_url');
        $this->appId = config('panier.app_id');
        $this->apiKey = config('panier.api_key');
        $this->timeout = config('panier.timeout', 30);
    }

    protected function client(): PendingRequest
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'APP-ID' => $this->appId,
            'API-KEY' => $this->apiKey,
        ])->timeout($this->timeout);
    }

    // ==================== PRODUCTS ====================

    public function createProducts(array $data, bool $overwriteDuplicates = true): Response
    {
        return $this->client()->post("{$this->baseUrl}/product/create", [
            'data' => $data,
            'overwrite_duplicates' => $overwriteDuplicates,
        ]);
    }

    public function updateProducts(array $data): Response
    {
        return $this->client()->put("{$this->baseUrl}/product/update", [
            'data' => $data,
        ]);
    }

    public function searchProducts(string $query = '*', int $limit = 10, int $skip = 0): Response
    {
        return $this->client()->post("{$this->baseUrl}/product/search", [
            'data' => [
                'query' => $query,
                'limit' => $limit,
                'skip' => $skip,
            ],
        ]);
    }

    public function deleteProducts(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/product/delete", [
            'data' => $data,
        ]);
    }

    // ==================== STOCKS ====================

    public function addStock(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/stock/add", [
            'data' => $data,
        ]);
    }

    public function subtractStock(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/stock/subtract", [
            'data' => $data,
        ]);
    }

    // ==================== CUSTOMERS ====================

    public function createCustomers(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/customer/create", [
            'data' => $data,
        ]);
    }

    public function updateCustomers(array $data): Response
    {
        return $this->client()->put("{$this->baseUrl}/customer/update", [
            'data' => $data,
        ]);
    }

    public function searchCustomers(string $query = '*', int $limit = 10, int $skip = 0): Response
    {
        return $this->client()->post("{$this->baseUrl}/customer/search", [
            'data' => [
                'query' => $query,
                'limit' => $limit,
                'skip' => $skip,
            ],
        ]);
    }

    public function deleteCustomers(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/customer/delete", [
            'data' => $data,
        ]);
    }

    // ==================== SUPPLIERS ====================

    public function createSuppliers(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/supplier/create", [
            'data' => $data,
        ]);
    }

    public function updateSuppliers(array $data): Response
    {
        return $this->client()->put("{$this->baseUrl}/supplier/update", [
            'data' => $data,
        ]);
    }

    public function searchSuppliers(string $query = '*', int $limit = 10, int $skip = 0): Response
    {
        return $this->client()->post("{$this->baseUrl}/supplier/search", [
            'data' => [
                'query' => $query,
                'limit' => $limit,
                'skip' => $skip,
            ],
        ]);
    }

    public function deleteSuppliers(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/supplier/delete", [
            'data' => $data,
        ]);
    }

    // ==================== TAXES ====================

    public function createTaxes(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/tax/create", [
            'data' => $data,
        ]);
    }

    public function updateTaxes(array $data): Response
    {
        return $this->client()->put("{$this->baseUrl}/tax/update", [
            'data' => $data,
        ]);
    }

    public function searchTaxes(string $query = '*', int $limit = 10, int $skip = 0): Response
    {
        return $this->client()->post("{$this->baseUrl}/tax/search", [
            'data' => [
                'query' => $query,
                'limit' => $limit,
                'skip' => $skip,
            ],
        ]);
    }

    public function deleteTaxes(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/tax/delete", [
            'data' => $data,
        ]);
    }

    // ==================== CURRENCIES ====================

    public function createCurrencies(array $data, bool $overwriteDuplicates = false): Response
    {
        return $this->client()->post("{$this->baseUrl}/currency/create", [
            'data' => $data,
            'overwrite_duplicates' => $overwriteDuplicates,
        ]);
    }

    public function updateCurrencies(array $data): Response
    {
        return $this->client()->put("{$this->baseUrl}/currency/update", [
            'data' => $data,
        ]);
    }

    public function searchCurrencies(string $query = '*', int $limit = 10, int $skip = 0): Response
    {
        return $this->client()->post("{$this->baseUrl}/currency/search", [
            'data' => [
                'query' => $query,
                'limit' => $limit,
                'skip' => $skip,
            ],
        ]);
    }

    public function deleteCurrencies(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/currency/delete", [
            'data' => $data,
        ]);
    }

    // ==================== SALES ====================

    public function createSale(array $data, bool $zimraFiscalize = false): Response
    {
        return $this->client()->post("{$this->baseUrl}/sale/create", [
            'data' => $data,
            'zimra_fiscalize' => $zimraFiscalize,
        ]);
    }

    public function checkPaymentStatus(string $saleId): Response
    {
        return $this->client()->get("{$this->baseUrl}/payment-provider/check-payment-status", [
            'id' => $saleId,
        ]);
    }

    public function confirmPayment(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/payment-provider/confirm-payment", [
            'data' => $data,
        ]);
    }

    public function searchSales(string $query = '*', int $limit = 10, int $skip = 0): Response
    {
        return $this->client()->post("{$this->baseUrl}/sale/search", [
            'data' => [
                'query' => $query,
                'limit' => $limit,
                'skip' => $skip,
            ],
        ]);
    }

    public function voidSale(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/sale/void", [
            'data' => $data,
        ]);
    }

    public function downloadSale(string $id): Response
    {
        return $this->client()->get("{$this->baseUrl}/sale/download", [
            'id' => $id,
        ]);
    }

    // ==================== INVOICES ====================

    public function createInvoices(array $data, bool $zimraFiscalize = false): Response
    {
        return $this->client()->post("{$this->baseUrl}/invoice/create", [
            'data' => $data,
            'zimra_fiscalize' => $zimraFiscalize,
        ]);
    }

    public function updateInvoices(array $data): Response
    {
        return $this->client()->put("{$this->baseUrl}/invoice/update", [
            'data' => $data,
        ]);
    }

    public function searchInvoices(string $query = '*', int $limit = 10, int $skip = 0): Response
    {
        return $this->client()->post("{$this->baseUrl}/invoice/search", [
            'data' => [
                'query' => $query,
                'limit' => $limit,
                'skip' => $skip,
            ],
        ]);
    }

    public function convertInvoiceToSale(array $data, bool $zimraFiscalize = false): Response
    {
        return $this->client()->post("{$this->baseUrl}/invoice/convert-to-sale", [
            'data' => $data,
            'zimra_fiscalize' => $zimraFiscalize,
        ]);
    }

    public function downloadInvoice(string $id): Response
    {
        return $this->client()->get("{$this->baseUrl}/invoice/download", [
            'id' => $id,
        ]);
    }

    public function deleteInvoices(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/invoice/delete", [
            'data' => $data,
        ]);
    }

    // ==================== DEBIT NOTES ====================

    public function createDebitNotes(array $data, bool $zimraFiscalize = false): Response
    {
        return $this->client()->post("{$this->baseUrl}/debit-note/create", [
            'data' => $data,
            'zimra_fiscalize' => $zimraFiscalize,
        ]);
    }

    public function searchDebitNotes(string $query = '*', int $limit = 10, int $skip = 0): Response
    {
        return $this->client()->post("{$this->baseUrl}/debit-note/search", [
            'data' => [
                'query' => $query,
                'limit' => $limit,
                'skip' => $skip,
            ],
        ]);
    }

    public function downloadDebitNote(string $id): Response
    {
        return $this->client()->get("{$this->baseUrl}/debit-note/download", [
            'id' => $id,
        ]);
    }

    public function deleteDebitNotes(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/debit-note/delete", [
            'data' => $data,
        ]);
    }

    // ==================== CREDIT NOTES ====================

    public function createCreditNotes(array $data, bool $zimraFiscalize = false): Response
    {
        return $this->client()->post("{$this->baseUrl}/credit-note/create", [
            'data' => $data,
            'zimra_fiscalize' => $zimraFiscalize,
        ]);
    }

    public function searchCreditNotes(string $query = '*', int $limit = 10, int $skip = 0): Response
    {
        return $this->client()->post("{$this->baseUrl}/credit-note/search", [
            'data' => [
                'query' => $query,
                'limit' => $limit,
                'skip' => $skip,
            ],
        ]);
    }

    public function downloadCreditNote(string $id): Response
    {
        return $this->client()->get("{$this->baseUrl}/credit-note/download", [
            'id' => $id,
        ]);
    }

    public function deleteCreditNotes(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/credit-note/delete", [
            'data' => $data,
        ]);
    }

    // ==================== DELIVERY NOTES ====================

    public function createDeliveryNotes(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/delivery-note/create", [
            'data' => $data,
        ]);
    }

    public function searchDeliveryNotes(string $query = '*', int $limit = 10, int $skip = 0): Response
    {
        return $this->client()->post("{$this->baseUrl}/delivery-note/search", [
            'data' => [
                'query' => $query,
                'limit' => $limit,
                'skip' => $skip,
            ],
        ]);
    }

    public function downloadDeliveryNote(string $id): Response
    {
        return $this->client()->get("{$this->baseUrl}/delivery-note/download", [
            'id' => $id,
        ]);
    }

    public function deleteDeliveryNotes(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/delivery-note/delete", [
            'data' => $data,
        ]);
    }

    // ==================== QUOTATIONS ====================

    public function createQuotations(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/quotation/create", [
            'data' => $data,
        ]);
    }

    public function updateQuotations(array $data): Response
    {
        return $this->client()->put("{$this->baseUrl}/quotation/update", [
            'data' => $data,
        ]);
    }

    public function searchQuotations(string $query = '*', int $limit = 10, int $skip = 0): Response
    {
        return $this->client()->post("{$this->baseUrl}/quotation/search", [
            'data' => [
                'query' => $query,
                'limit' => $limit,
                'skip' => $skip,
            ],
        ]);
    }

    public function convertQuotationToInvoice(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/quotation/convert-to-invoice", [
            'data' => $data,
        ]);
    }

    public function convertQuotationToSale(array $data, bool $zimraFiscalize = false): Response
    {
        return $this->client()->post("{$this->baseUrl}/quotation/convert-to-sale", [
            'data' => $data,
            'zimra_fiscalize' => $zimraFiscalize,
        ]);
    }

    public function downloadQuotation(string $id): Response
    {
        return $this->client()->get("{$this->baseUrl}/quotation/download", [
            'id' => $id,
        ]);
    }

    public function deleteQuotations(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/quotation/delete", [
            'data' => $data,
        ]);
    }

    // ==================== ZIMRA FISCALISATION ====================

    public function zimraOpenDay(): Response
    {
        return $this->client()->get("{$this->baseUrl}/zimra/open-day");
    }

    public function zimraCloseDay(): Response
    {
        return $this->client()->get("{$this->baseUrl}/zimra/close-day");
    }

    public function zimraFiscalize(array $data): Response
    {
        return $this->client()->post("{$this->baseUrl}/zimra/fiscalize", [
            'data' => $data,
        ]);
    }
}
