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
use Illuminate\Support\Str;

class DatabaseService
{
    // ==================== PRODUCTS ====================

    public function createProducts(array $data, bool $overwriteDuplicates = true): array
    {
        $created = [];
        $excludedDuplicates = [];

        foreach ($data as $productData) {
            $existing = PanierProduct::where('name', $productData['name'])->first();
            
            if ($existing && !$overwriteDuplicates) {
                $excludedDuplicates[] = $existing->toArray();
                continue;
            }

            $product = PanierProduct::updateOrCreate(
                ['name' => $productData['name']],
                [
                    'panier_id' => $existing?->panier_id ?? Str::ulid()->toString(),
                    'description' => $productData['description'] ?? null,
                    'buying_price' => $productData['buying_price'] ?? 0,
                    'selling_price' => $productData['selling_price'] ?? 0,
                    'quantity' => $productData['initial_quantity'] ?? $productData['quantity'] ?? 0,
                    'hs_code' => $productData['hs_code'] ?? null,
                    'sku' => $productData['sku'] ?? null,
                    'is_inventory_item' => $productData['is_inventory_item'] ?? true,
                    'applicable_tax_id' => $productData['applicable_tax_id'] ?? null,
                    'suppliers' => $productData['suppliers'] ?? null,
                    'panier_data' => $productData,
                ]
            );
            $created[] = $this->formatProduct($product);
        }

        return [
            'created' => $created,
            'excluded_duplicates' => $excludedDuplicates,
        ];
    }

    public function updateProducts(array $data): array
    {
        $updated = [];

        foreach ($data as $productData) {
            $product = PanierProduct::where('panier_id', $productData['id'])->first();
            
            if (!$product) {
                continue;
            }

            $product->update(array_filter([
                'name' => $productData['name'] ?? null,
                'description' => $productData['description'] ?? null,
                'buying_price' => $productData['buying_price'] ?? null,
                'selling_price' => $productData['selling_price'] ?? null,
                'hs_code' => $productData['hs_code'] ?? null,
                'sku' => $productData['sku'] ?? null,
                'is_inventory_item' => $productData['is_inventory_item'] ?? null,
                'applicable_tax_id' => $productData['applicable_tax_id'] ?? null,
                'suppliers' => $productData['suppliers'] ?? null,
            ], fn($v) => $v !== null));

            $updated[] = $this->formatProduct($product->fresh());
        }

        return ['updated' => $updated];
    }

    public function searchProducts(string $query = '*', int $limit = 10, int $skip = 0): array
    {
        $queryBuilder = PanierProduct::query();

        if ($query !== '*') {
            $queryBuilder->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%");
            });
        }

        $total = $queryBuilder->count();
        $products = $queryBuilder->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        return [
            'searched_record_count' => $products->count(),
            'total_record_count' => $total,
            'searched' => $products->map(fn($p) => $this->formatProduct($p))->toArray(),
        ];
    }

    public function deleteProducts(array $data): array
    {
        $ids = array_column($data, 'id');
        $count = PanierProduct::whereIn('panier_id', $ids)->delete();

        return ['deleted_count' => $count];
    }

    protected function formatProduct(PanierProduct $product): array
    {
        // Get the tax relationship
        $tax = $product->tax;
        $applicableTax = null;
        if ($tax) {
            $applicableTax = [
                'id' => $tax->panier_id,
                'name' => $tax->name,
                'percentage' => (float) $tax->percentage,
                'code' => $tax->code,
                'zimra_tax_id' => $tax->zimra_tax_id,
            ];
        }

        // Get suppliers as objects
        $supplierObjects = [];
        if ($product->suppliers && is_array($product->suppliers)) {
            $suppliers = PanierSupplier::whereIn('panier_id', $product->suppliers)->get();
            foreach ($suppliers as $supplier) {
                $supplierObjects[] = [
                    'id' => $supplier->panier_id,
                    'name' => $supplier->name,
                ];
            }
        }

        return [
            'id' => $product->panier_id,
            'name' => $product->name,
            'description' => $product->description,
            'buying_price' => (float) $product->buying_price,
            'sku' => $product->sku,
            'hs_code' => $product->hs_code,
            'selling_price' => (float) $product->selling_price,
            'quantity' => $product->quantity,
            'is_inventory_item' => $product->is_inventory_item,
            'applicable_tax' => $applicableTax,
            'supplier' => $supplierObjects,
            'updated_at' => $product->updated_at?->toIso8601String(),
            'created_at' => $product->created_at?->toIso8601String(),
        ];
    }

    // ==================== STOCKS ====================

    public function addStock(array $data): array
    {
        $updated = [];

        foreach ($data as $stockData) {
            $product = PanierProduct::where('panier_id', $stockData['id'])->first();
            if ($product) {
                $product->increment('quantity', $stockData['quantity'] ?? 0);
                $updated[] = $this->formatProduct($product->fresh());
            }
        }

        return ['updated' => $updated];
    }

    public function subtractStock(array $data): array
    {
        $updated = [];

        foreach ($data as $stockData) {
            $product = PanierProduct::where('panier_id', $stockData['id'])->first();
            if ($product) {
                $product->decrement('quantity', $stockData['quantity'] ?? 0);
                $updated[] = $this->formatProduct($product->fresh());
            }
        }

        return ['updated' => $updated];
    }

    // ==================== CUSTOMERS ====================

    public function createCustomers(array $data, bool $overwriteDuplicates = false): array
    {
        $created = [];
        $excludedDuplicates = [];

        foreach ($data as $customerData) {
            $existing = PanierCustomer::where('email', $customerData['email'] ?? '')->first();
            
            if ($existing && !$overwriteDuplicates) {
                $excludedDuplicates[] = $existing->toArray();
                continue;
            }

            $customer = PanierCustomer::updateOrCreate(
                ['email' => $customerData['email'] ?? Str::ulid()->toString() . '@temp.local'],
                [
                    'panier_id' => $existing?->panier_id ?? Str::ulid()->toString(),
                    'name' => $customerData['name'],
                    'phone' => $customerData['phone'] ?? null,
                    'address' => $customerData['address'] ?? null,
                    'tax_id' => $customerData['tax_id'] ?? $customerData['tax_reg_number'] ?? $customerData['tin_number'] ?? null,
                    'panier_data' => $customerData,
                ]
            );
            $created[] = $this->formatCustomer($customer);
        }

        return [
            'created' => $created,
            'excluded_duplicates' => $excludedDuplicates,
        ];
    }

    public function updateCustomers(array $data): array
    {
        $updated = [];

        foreach ($data as $customerData) {
            $customer = PanierCustomer::where('panier_id', $customerData['id'])->first();
            
            if (!$customer) {
                continue;
            }

            $customer->update(array_filter([
                'name' => $customerData['name'] ?? null,
                'email' => $customerData['email'] ?? null,
                'phone' => $customerData['phone'] ?? null,
                'address' => $customerData['address'] ?? null,
                'tax_id' => $customerData['tax_id'] ?? null,
            ], fn($v) => $v !== null));

            $updated[] = $this->formatCustomer($customer->fresh());
        }

        return ['updated' => $updated];
    }

    public function searchCustomers(string $query = '*', int $limit = 10, int $skip = 0): array
    {
        $queryBuilder = PanierCustomer::query();

        if ($query !== '*') {
            $queryBuilder->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%");
            });
        }

        $total = $queryBuilder->count();
        $customers = $queryBuilder->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        return [
            'searched_record_count' => $customers->count(),
            'total_record_count' => $total,
            'searched' => $customers->map(fn($c) => $this->formatCustomer($c))->toArray(),
        ];
    }

    public function deleteCustomers(array $data): array
    {
        $ids = array_column($data, 'id');
        $count = PanierCustomer::whereIn('panier_id', $ids)->delete();

        return ['deleted_count' => $count];
    }

    protected function formatCustomer(PanierCustomer $customer): array
    {
        return [
            'id' => $customer->panier_id,
            '_id' => $customer->panier_id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'tax_id' => $customer->tax_id,
            'created_at' => $customer->created_at?->toIso8601String(),
            'updated_at' => $customer->updated_at?->toIso8601String(),
        ];
    }

    // ==================== SUPPLIERS ====================

    public function createSuppliers(array $data): array
    {
        $created = [];

        foreach ($data as $supplierData) {
            $supplier = PanierSupplier::create([
                'panier_id' => Str::ulid()->toString(),
                'name' => $supplierData['name'],
                'email' => $supplierData['email'] ?? null,
                'phone' => $supplierData['phone'] ?? null,
                'address' => $supplierData['address'] ?? null,
                'panier_data' => $supplierData,
            ]);
            $created[] = $this->formatSupplier($supplier);
        }

        return ['created' => $created];
    }

    public function updateSuppliers(array $data): array
    {
        $updated = [];

        foreach ($data as $supplierData) {
            $supplier = PanierSupplier::where('panier_id', $supplierData['id'])->first();
            
            if (!$supplier) {
                continue;
            }

            $supplier->update(array_filter([
                'name' => $supplierData['name'] ?? null,
                'email' => $supplierData['email'] ?? null,
                'phone' => $supplierData['phone'] ?? null,
                'address' => $supplierData['address'] ?? null,
            ], fn($v) => $v !== null));

            $updated[] = $this->formatSupplier($supplier->fresh());
        }

        return ['updated' => $updated];
    }

    public function searchSuppliers(string $query = '*', int $limit = 10, int $skip = 0): array
    {
        $queryBuilder = PanierSupplier::query();

        if ($query !== '*') {
            $queryBuilder->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            });
        }

        $total = $queryBuilder->count();
        $suppliers = $queryBuilder->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        return [
            'searched_record_count' => $suppliers->count(),
            'total_record_count' => $total,
            'searched' => $suppliers->map(fn($s) => $this->formatSupplier($s))->toArray(),
        ];
    }

    public function deleteSuppliers(array $data): array
    {
        $ids = array_column($data, 'id');
        $count = PanierSupplier::whereIn('panier_id', $ids)->delete();

        return ['deleted_count' => $count];
    }

    protected function formatSupplier(PanierSupplier $supplier): array
    {
        return [
            'id' => $supplier->panier_id,
            '_id' => $supplier->panier_id,
            'name' => $supplier->name,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
            'address' => $supplier->address,
            'created_at' => $supplier->created_at?->toIso8601String(),
            'updated_at' => $supplier->updated_at?->toIso8601String(),
        ];
    }

    // ==================== TAXES ====================

    public function createTaxes(array $data): array
    {
        $created = [];

        foreach ($data as $taxData) {
            $tax = PanierTax::create([
                'panier_id' => Str::ulid()->toString(),
                'name' => $taxData['name'],
                'percentage' => $taxData['percentage'] ?? 0,
                'code' => $taxData['code'] ?? null,
                'zimra_tax_id' => $taxData['zimra_tax_id'] ?? null,
                'panier_data' => $taxData,
            ]);
            $created[] = $this->formatTax($tax);
        }

        return ['created' => $created];
    }

    public function updateTaxes(array $data): array
    {
        $updated = [];

        foreach ($data as $taxData) {
            $tax = PanierTax::where('panier_id', $taxData['id'])->first();
            
            if (!$tax) {
                continue;
            }

            $tax->update(array_filter([
                'name' => $taxData['name'] ?? null,
                'percentage' => $taxData['percentage'] ?? null,
                'code' => $taxData['code'] ?? null,
            ], fn($v) => $v !== null));

            $updated[] = $this->formatTax($tax->fresh());
        }

        return ['updated' => $updated];
    }

    public function searchTaxes(string $query = '*', int $limit = 10, int $skip = 0): array
    {
        $queryBuilder = PanierTax::query();

        if ($query !== '*') {
            $queryBuilder->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('code', 'like', "%{$query}%");
            });
        }

        $total = $queryBuilder->count();
        $taxes = $queryBuilder->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        return [
            'searched_record_count' => $taxes->count(),
            'total_record_count' => $total,
            'searched' => $taxes->map(fn($t) => $this->formatTax($t))->toArray(),
        ];
    }

    public function deleteTaxes(array $data): array
    {
        $ids = array_column($data, 'id');
        $count = PanierTax::whereIn('panier_id', $ids)->delete();

        return ['deleted_count' => $count];
    }

    protected function formatTax(PanierTax $tax): array
    {
        return [
            'id' => $tax->panier_id,
            '_id' => $tax->panier_id,
            'name' => $tax->name,
            'percentage' => (float) $tax->percentage,
            'code' => $tax->code,
            'created_at' => $tax->created_at?->toIso8601String(),
            'updated_at' => $tax->updated_at?->toIso8601String(),
        ];
    }

    // ==================== CURRENCIES ====================

    public function createCurrencies(array $data, bool $overwriteDuplicates = false): array
    {
        $created = [];

        foreach ($data as $currencyData) {
            $existing = PanierCurrency::where('code', $currencyData['code'])->first();
            
            if ($existing && !$overwriteDuplicates) {
                continue;
            }

            $currency = PanierCurrency::updateOrCreate(
                ['code' => $currencyData['code']],
                [
                    'panier_id' => $existing?->panier_id ?? Str::ulid()->toString(),
                    'name' => $currencyData['name'] ?? $currencyData['code'],
                    'exchange_rate' => $currencyData['exchange_rate'] ?? 1,
                    'panier_data' => $currencyData,
                ]
            );
            $created[] = $this->formatCurrency($currency);
        }

        return ['created' => $created];
    }

    public function updateCurrencies(array $data): array
    {
        $updated = [];

        foreach ($data as $currencyData) {
            $currency = PanierCurrency::where('panier_id', $currencyData['id'])->first();
            
            if (!$currency) {
                continue;
            }

            $currency->update(array_filter([
                'code' => $currencyData['code'] ?? null,
                'name' => $currencyData['name'] ?? null,
                'exchange_rate' => $currencyData['exchange_rate'] ?? null,
            ], fn($v) => $v !== null));

            $updated[] = $this->formatCurrency($currency->fresh());
        }

        return ['updated' => $updated];
    }

    public function searchCurrencies(string $query = '*', int $limit = 10, int $skip = 0): array
    {
        $queryBuilder = PanierCurrency::query();

        if ($query !== '*') {
            $queryBuilder->where(function ($q) use ($query) {
                $q->where('code', 'like', "%{$query}%")
                  ->orWhere('name', 'like', "%{$query}%");
            });
        }

        $total = $queryBuilder->count();
        $currencies = $queryBuilder->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        return [
            'searched_record_count' => $currencies->count(),
            'total_record_count' => $total,
            'searched' => $currencies->map(fn($c) => $this->formatCurrency($c))->toArray(),
        ];
    }

    public function deleteCurrencies(array $data): array
    {
        $ids = array_column($data, 'id');
        $count = PanierCurrency::whereIn('panier_id', $ids)->delete();

        return ['deleted_count' => $count];
    }

    protected function formatCurrency(PanierCurrency $currency): array
    {
        return [
            'id' => $currency->panier_id,
            '_id' => $currency->panier_id,
            'code' => $currency->code,
            'name' => $currency->name,
            'exchange_rate' => (float) $currency->exchange_rate,
            'created_at' => $currency->created_at?->toIso8601String(),
            'updated_at' => $currency->updated_at?->toIso8601String(),
        ];
    }

    // ==================== SALES ====================

    public function createSale(array $data, bool $zimraFiscalize = false, array $paymentMethod = []): array
    {
        $saleId = Str::ulid()->toString();
        
        $total = 0;
        $taxTotal = 0;
        $products = $data['products'] ?? [];
        
        foreach ($products as $productItem) {
            $productPrice = $productItem['selling_price'] * $productItem['quantity'];
            $discount = $productItem['discount'] ?? 0;
            $total += $productPrice - $discount;
        }

        $sale = PanierSale::create([
            'panier_id' => $saleId,
            'customer_id' => $data['customer_id'] ?? null,
            'currency_id' => $data['currency_id'] ?? null,
            'total' => $total,
            'tax_total' => $taxTotal,
            'payment_method' => $paymentMethod['type'] ?? null,
            'payment_status' => 'completed',
            'is_voided' => false,
            'zimra_fiscalized' => $zimraFiscalize,
            'products' => $products,
            'panier_data' => $data,
            'sale_date' => now(),
        ]);

        return ['created' => $this->formatSale($sale)];
    }

    public function searchSales(string $query = '*', int $limit = 10, int $skip = 0): array
    {
        $queryBuilder = PanierSale::query();

        if ($query !== '*') {
            $queryBuilder->where(function ($q) use ($query) {
                $q->where('panier_id', 'like', "%{$query}%")
                  ->orWhere('payment_method', 'like', "%{$query}%");
            });
        }

        $total = $queryBuilder->count();
        $sales = $queryBuilder->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        return [
            'searched_record_count' => $sales->count(),
            'total_record_count' => $total,
            'searched' => $sales->map(fn($s) => $this->formatSale($s))->toArray(),
        ];
    }

    public function voidSale(array $data): array
    {
        $sale = PanierSale::where('panier_id', $data['id'])->first();
        
        if (!$sale) {
            return ['error' => 'Sale not found'];
        }

        $sale->update([
            'is_voided' => true,
            'void_reason' => $data['reason'] ?? null,
        ]);

        return ['voided' => $this->formatSale($sale->fresh())];
    }

    protected function formatSale(PanierSale $sale): array
    {
        return [
            'id' => $sale->panier_id,
            '_id' => $sale->panier_id,
            'customer_id' => $sale->customer_id,
            'currency_id' => $sale->currency_id,
            'total' => (float) $sale->total,
            'tax_total' => (float) $sale->tax_total,
            'payment_method' => $sale->payment_method,
            'payment_status' => $sale->payment_status,
            'is_voided' => $sale->is_voided,
            'void_reason' => $sale->void_reason,
            'zimra_fiscalized' => $sale->zimra_fiscalized,
            'zimra_fiscal_code' => $sale->zimra_fiscal_code,
            'products' => $sale->products,
            'sale_date' => $sale->sale_date?->toIso8601String(),
            'created_at' => $sale->created_at?->toIso8601String(),
            'updated_at' => $sale->updated_at?->toIso8601String(),
        ];
    }

    // ==================== INVOICES ====================

    public function createInvoices(array $data, bool $zimraFiscalize = false): array
    {
        $created = [];

        foreach ($data as $invoiceData) {
            $invoiceId = Str::ulid()->toString();
            
            $total = 0;
            $taxTotal = 0;
            $products = $invoiceData['products'] ?? [];
            
            foreach ($products as $productItem) {
                $productPrice = $productItem['selling_price'] * $productItem['quantity'];
                $discount = $productItem['discount'] ?? 0;
                $total += $productPrice - $discount;
            }

            $invoice = PanierInvoice::create([
                'panier_id' => $invoiceId,
                'invoice_number' => 'INV-' . strtoupper(substr($invoiceId, -8)),
                'customer_id' => $invoiceData['customer_id'] ?? null,
                'currency_id' => $invoiceData['currency_id'] ?? null,
                'total' => $total,
                'tax_total' => $taxTotal,
                'status' => 'pending',
                'zimra_fiscalized' => $zimraFiscalize,
                'products' => $products,
                'panier_data' => $invoiceData,
                'due_date' => $invoiceData['payment_due'] ?? null,
                'invoice_date' => now(),
            ]);
            
            $created[] = $this->formatInvoice($invoice);
        }

        return ['created' => $created];
    }

    public function updateInvoices(array $data): array
    {
        $updated = [];

        foreach ($data as $invoiceData) {
            $invoice = PanierInvoice::where('panier_id', $invoiceData['id'])->first();
            
            if (!$invoice) {
                continue;
            }

            $invoice->update(array_filter([
                'customer_id' => $invoiceData['customer_id'] ?? null,
                'currency_id' => $invoiceData['currency_id'] ?? null,
                'status' => $invoiceData['status'] ?? null,
                'due_date' => $invoiceData['due_date'] ?? null,
                'products' => $invoiceData['products'] ?? null,
            ], fn($v) => $v !== null));

            $updated[] = $this->formatInvoice($invoice->fresh());
        }

        return ['updated' => $updated];
    }

    public function searchInvoices(string $query = '*', int $limit = 10, int $skip = 0): array
    {
        $queryBuilder = PanierInvoice::query();

        if ($query !== '*') {
            $queryBuilder->where(function ($q) use ($query) {
                $q->where('invoice_number', 'like', "%{$query}%")
                  ->orWhere('panier_id', 'like', "%{$query}%");
            });
        }

        $total = $queryBuilder->count();
        $invoices = $queryBuilder->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        return [
            'searched_record_count' => $invoices->count(),
            'total_record_count' => $total,
            'searched' => $invoices->map(fn($i) => $this->formatInvoice($i))->toArray(),
        ];
    }

    public function deleteInvoices(array $data): array
    {
        $ids = array_column($data, 'id');
        $count = PanierInvoice::whereIn('panier_id', $ids)->delete();

        return ['deleted_count' => $count];
    }

    public function convertInvoiceToSale(array $data, bool $zimraFiscalize = false): array
    {
        $invoice = PanierInvoice::where('panier_id', $data['invoice_id'])->first();
        
        if (!$invoice) {
            return ['error' => 'Invoice not found'];
        }

        $saleResult = $this->createSale([
            'customer_id' => $invoice->customer_id,
            'currency_id' => $invoice->currency_id,
            'products' => $invoice->products,
        ], $zimraFiscalize, ['type' => $data['payment_method'] ?? 'cash']);

        $invoice->update(['status' => 'paid']);

        return [
            'sale' => $saleResult['created'],
            'invoice' => $this->formatInvoice($invoice->fresh()),
        ];
    }

    protected function formatInvoice(PanierInvoice $invoice): array
    {
        return [
            'id' => $invoice->panier_id,
            '_id' => $invoice->panier_id,
            'invoice_number' => $invoice->invoice_number,
            'customer_id' => $invoice->customer_id,
            'currency_id' => $invoice->currency_id,
            'total' => (float) $invoice->total,
            'tax_total' => (float) $invoice->tax_total,
            'status' => $invoice->status,
            'zimra_fiscalized' => $invoice->zimra_fiscalized,
            'zimra_fiscal_code' => $invoice->zimra_fiscal_code,
            'products' => $invoice->products,
            'due_date' => $invoice->due_date,
            'invoice_date' => $invoice->invoice_date?->toIso8601String(),
            'created_at' => $invoice->created_at?->toIso8601String(),
            'updated_at' => $invoice->updated_at?->toIso8601String(),
        ];
    }

    // ==================== DEBIT NOTES ====================

    public function createDebitNote(array $noteData, bool $zimraFiscalize = true): array
    {
        // Delegate to DebitNoteService
        $debitNoteService = app(\App\Services\DebitNoteService::class);
        return $debitNoteService->createDebitNote($noteData);
    }

    protected function submitDebitNoteToZimra(\App\Models\Receipt $originalReceipt, array $noteData): array
    {
        $zimraService = app(\App\Services\ZimraDeviceService::class);
        $zimraConfig = \App\Models\ZimraConfig::getActive();
        
        $deviceId = $zimraConfig->device_id;
        $currencyCode = $originalReceipt->receipt_currency;

        // Step 1: Load original receipt lines for delta calculation
        $originalLines = $originalReceipt->receipt_lines ?? [];
        if (is_string($originalLines)) {
            $originalLines = json_decode($originalLines, true) ?? [];
        }
        $originalLinesByName = [];
        foreach ($originalLines as $line) {
            $lineName = $line['receiptLineName'] ?? '';
            if ($lineName) {
                $originalLinesByName[$lineName] = $line;
            }
        }

        $products = $noteData['products'] ?? [];
        $receiptLines = [];
        $receiptTaxes = [];
        $taxGroups = [];
        $receiptTotal = 0;

        foreach ($products as $product) {
            // Check if product has line item details (from receipt) or needs lookup (legacy)
            if (isset($product['price']) && isset($product['name'])) {
                // New format: line item details from original receipt
                $quantity = $product['quantity'] ?? 1;
                $price = abs(floatval($product['price']));
                $taxPercent = floatval($product['taxPercent'] ?? 0);
                $taxId = intval($product['taxID'] ?? 1);
                $taxCode = $product['taxCode'] ?? null;
                $hsCode = $product['receiptLineHSCode'] ?? '';
                $name = $product['name'];
            } else {
                // Legacy format: lookup product by ID
                $productModel = PanierProduct::where('panier_id', $product['id'])->first();
                
                if (!$productModel) {
                    throw new \Exception("Product {$product['id']} not found");
                }

                $quantity = $product['quantity'] ?? 1;
                $price = abs($productModel->selling_price ?? 0);
                
                $tax = $productModel->tax;
                if (!$tax) {
                    throw new \Exception("Product {$productModel->name} does not have a tax assigned");
                }

                $taxPercent = (float) $tax->percentage;
                $taxId = $tax->zimra_tax_id;
                $taxCode = $tax->code ?? null;
                $hsCode = $productModel->hs_code ?? '';
                $name = $productModel->name;
            }

            // Step 2: Calculate delta (correctedQty - originalQty)
            $originalQty = isset($originalLinesByName[$name]) ? floatval($originalLinesByName[$name]['receiptLineQuantity'] ?? 0) : 0;
            $deltaQty = round($quantity - $originalQty, 2);
            if ($deltaQty <= 0) { continue; }
            $quantity = $deltaQty;

            // For debit notes, use tax-exclusive pricing with BCMath for precision
            // FDMS spec for receiptLinesTaxInclusive = false:
            // - receiptLineTotal = base amount (WITHOUT tax)
            // - taxAmount = SUM(receiptLineTotal) * (taxPercent/100)
            // - salesAmountWithTax = SUM(receiptLineTotal) + taxAmount
            bcscale(2);
            $lineTotal = bcmul((string)$price, (string)$quantity, 2); // Base amount WITHOUT tax
            $taxPercentDecimal = bcdiv((string)$taxPercent, '100', 4); // Convert 15 to 0.15
            $taxAmount = bcmul($lineTotal, $taxPercentDecimal, 2);
            $salesAmount = bcadd($lineTotal, $taxAmount, 2); // base + tax

            $receiptLines[] = [
                'receiptLineType' => 'Sale',
                'receiptLineNo' => count($receiptLines) + 1,
                'receiptLineHSCode' => $hsCode,
                'receiptLineName' => $name,
                'receiptLinePrice' => (float)$price,
                'receiptLineQuantity' => (float)$quantity,
                'receiptLineTotal' => (float)$lineTotal, // Base amount WITHOUT tax
                'taxPercent' => (float)$taxPercent,
                'taxID' => $taxId,
            ];

            if ($taxCode) {
                $receiptLines[count($receiptLines) - 1]['taxCode'] = $taxCode;
            }

            $taxKey = "{$taxId}_{$taxPercent}";
            if (!isset($taxGroups[$taxKey])) {
                $taxGroups[$taxKey] = [
                    'taxID' => $taxId,
                    'taxPercent' => (float)$taxPercent,
                    'taxAmount' => 0.0,
                    'salesAmountWithTax' => 0.0,
                ];
            }
            $taxGroups[$taxKey]['taxAmount'] = bcadd((string)$taxGroups[$taxKey]['taxAmount'], $taxAmount, 2);
            $taxGroups[$taxKey]['salesAmountWithTax'] = bcadd((string)$taxGroups[$taxKey]['salesAmountWithTax'], $salesAmount, 2);
            $receiptTotal = bcadd((string)$receiptTotal, $salesAmount, 2); // Total includes tax
        }

        foreach ($taxGroups as $tax) {
            $receiptTaxes[] = [
                'taxID' => $tax['taxID'],
                'taxPercent' => $tax['taxPercent'],
                'taxAmount' => round($tax['taxAmount'], 2),
                'salesAmountWithTax' => round($tax['salesAmountWithTax'], 2),
            ];
        }

        $receiptPayments = [
            [
                'moneyTypeCode' => 'Cash',
                'paymentAmount' => round($receiptTotal, 2),
            ]
        ];

        // Debug logging BEFORE building final payload
        \Log::info('DEBIT_NOTE_PAYLOAD_DEBUG', [
            'original_receipt' => [
                'id' => $originalReceipt->id,
                'invoice_no' => $originalReceipt->invoice_no,
                'receipt_global_no' => $originalReceipt->receipt_global_no,
                'receipt_date' => $originalReceipt->receipt_date,
                'fdms_receipt_id' => $originalReceipt->fdms_receipt_id,
            ],
            'receiptLines' => $receiptLines,
            'receiptTaxes' => $receiptTaxes,
            'receiptTotal' => $receiptTotal,
            'receiptLinesTaxInclusive' => false,
        ]);

        // Validate: DebitNote must have positive delta lines
        if (empty($receiptLines)) {
            throw new \Exception('Invalid DebitNote: no positive delta (new qty must exceed original qty)');
        }

        $receiptData = [
            'receiptType' => 'DebitNote',
            'receiptCurrency' => $currencyCode,
            'invoiceNo' => $noteData['invoice_no'] ?? 'DN-' . time(),
            'receiptDate' => now()->format('Y-m-d\TH:i:s'),
            'receiptLinesTaxInclusive' => false,
            'receiptLines' => $receiptLines,
            'receiptTaxes' => $receiptTaxes,
            'receiptPayments' => $receiptPayments,
            'receiptTotal' => (float)$receiptTotal,
            'receiptPrintForm' => 'Receipt48',
            'receiptNotes' => 'Quantity adjustment',
            'creditDebitNote' => [
                'creditDebitNoteReceiptGlobalNo' => $originalReceipt->receipt_global_no,
                'creditDebitNoteDate' => $originalReceipt->receipt_date instanceof \Carbon\Carbon
                    ? $originalReceipt->receipt_date->format('Y-m-d\TH:i:s')
                    : $originalReceipt->receipt_date,
            ],
            'original_receipt_id' => $originalReceipt->id,
            'external_reference' => $noteData['external_reference'] ?? null,
        ];

        if (isset($noteData['customer_id'])) {
            $customer = PanierCustomer::where('panier_id', $noteData['customer_id'])->first();
            if ($customer) {
                $receiptData['buyerData'] = [
                    'buyerRegisterName' => $customer->name,
                    'buyerTradeName' => $customer->name,
                    'buyerTIN' => $customer->tax_id ?? '',
                    'buyerContacts' => [
                        'phoneNo' => $customer->phone ?? '',
                        'email' => $customer->email ?? '',
                    ],
                    'buyerAddress' => [
                        'street' => $customer->address ?? '',
                        'city' => '',
                        'country' => 'ZW',
                    ],
                ];
            }
        }

        $result = $zimraService->submitReceipt($receiptData, $deviceId);
        
        return [
            'success' => !isset($result['error']),
            'receipt' => $result['receipt'] ?? null,
            'fdms_receipt_id' => $result['fdms_receipt_id'] ?? null,
            'error' => $result['error'] ?? null,
            'message' => $result['message'] ?? null,
        ];
    }

    public function searchDebitNotes(string $query = '*', int $limit = 10, int $skip = 0): array
    {
        $queryBuilder = PanierDebitNote::query();

        if ($query !== '*') {
            $queryBuilder->where('panier_id', 'like', "%{$query}%");
        }

        $total = $queryBuilder->count();
        $notes = $queryBuilder->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        return [
            'searched_record_count' => $notes->count(),
            'total_record_count' => $total,
            'searched' => $notes->map(fn($n) => $this->formatDebitNote($n))->toArray(),
        ];
    }

    public function deleteDebitNotes(array $data): array
    {
        $ids = array_column($data, 'id');
        $count = PanierDebitNote::whereIn('panier_id', $ids)->delete();

        return ['deleted_count' => $count];
    }

    protected function formatDebitNote(PanierDebitNote $note): array
    {
        return [
            'id' => $note->panier_id,
            '_id' => $note->panier_id,
            'invoice_id' => $note->invoice_id,
            'customer_id' => $note->customer_id,
            'total' => (float) $note->total,
            'reason' => $note->reason,
            'zimra_fiscalized' => $note->zimra_fiscalized,
            'zimra_fiscal_code' => $note->zimra_fiscal_code,
            'products' => $note->products,
            'created_at' => $note->created_at?->toIso8601String(),
            'updated_at' => $note->updated_at?->toIso8601String(),
        ];
    }

    // ==================== CREDIT NOTES ====================

    public function createCreditNotes(array $data, bool $zimraFiscalize = false): array
    {
        $created = [];
        $zimraErrors = [];

        foreach ($data as $noteData) {
            $saleId = $noteData['sale_id'] ?? null;
            
            if (!$saleId) {
                throw new \Exception('sale_id is required for credit note creation');
            }

            $sale = PanierSale::where('panier_id', $saleId)->first();
            if (!$sale) {
                throw new \Exception("Sale with ID {$saleId} not found");
            }

            $products = $noteData['products'] ?? [];
            $total = 0;
            
            foreach ($products as $product) {
                $quantity = $product['quantity'] ?? 0;
                $price = $product['selling_price'] ?? 0;
                $total += ($quantity * $price);
            }

            $note = PanierCreditNote::create([
                'panier_id' => Str::ulid()->toString(),
                'sale_id' => $saleId,
                'customer_id' => $noteData['customer_id'] ?? $sale->customer_id,
                'total' => -abs($total),
                'reason' => $noteData['reason'] ?? null,
                'zimra_fiscalized' => $zimraFiscalize,
                'products' => $products,
                'panier_data' => $noteData,
            ]);

            if ($zimraFiscalize) {
                try {
                    $zimraResult = $this->submitCreditNoteToZimra($note, $sale, $noteData);
                    
                    if (isset($zimraResult['error'])) {
                        $zimraErrors[] = [
                            'credit_note_id' => $note->panier_id,
                            'error' => $zimraResult,
                        ];
                    } else {
                        $note->update([
                            'zimra_fiscal_code' => $zimraResult['fdms_receipt_id'] ?? null,
                        ]);
                    }
                } catch (\Exception $e) {
                    $zimraErrors[] = [
                        'credit_note_id' => $note->panier_id,
                        'error' => $e->getMessage(),
                    ];
                }
            }
            
            $created[] = $this->formatCreditNote($note->fresh());
        }

        $result = ['created' => $created];
        
        if (!empty($zimraErrors)) {
            $result['zimra_errors'] = $zimraErrors;
        }

        return $result;
    }

    protected function submitCreditNoteToZimra(PanierCreditNote $creditNote, PanierSale $sale, array $noteData): array
    {
        $zimraService = app(\App\Services\ZimraDeviceService::class);
        $zimraConfig = \App\Models\ZimraConfig::getActive();
        
        if (!$zimraConfig || !$zimraConfig->device_id) {
            throw new \Exception('No active ZIMRA configuration found');
        }

        $deviceId = $zimraConfig->device_id;
        
        $originalReceipt = \App\Models\Receipt::where('device_id', $deviceId)
            ->where('invoice_no', $sale->panier_id)
            ->orWhere('invoice_no', 'LIKE', '%' . substr($sale->panier_id, -8))
            ->first();

        if (!$originalReceipt) {
            throw new \Exception(
                "Original receipt not found for sale {$sale->panier_id}. " .
                "Cannot create credit note without original ZIMRA receipt."
            );
        }

        $currency = $sale->currency ? \App\Models\PanierCurrency::where('panier_id', $sale->currency_id)->first() : null;
        $currencyCode = $currency?->code ?? 'USD';

        $receiptLines = [];
        $receiptTaxes = [];
        $taxGroups = [];

        foreach ($creditNote->products as $product) {
            $productModel = PanierProduct::where('panier_id', $product['id'])->first();
            $quantity = $product['quantity'] ?? 1;
            $price = -abs($product['selling_price'] ?? 0);
            
            $tax = $productModel?->tax;
            $taxPercent = $tax ? (float) $tax->percentage : 0;
            $taxId = $tax?->zimra_tax_id ?? 1;
            $taxCode = $tax?->code ?? 'A';

            $lineTotal = $price * $quantity;
            $taxAmount = $lineTotal * ($taxPercent / (100 + $taxPercent));
            $lineAmountWithoutTax = $lineTotal - $taxAmount;

            $receiptLines[] = [
                'receiptLineType' => 'Sale',
                'receiptLineNo' => count($receiptLines) + 1,
                'receiptLineHSCode' => $productModel?->hs_code ?? '',
                'receiptLineName' => $productModel?->name ?? $product['name'] ?? 'Product',
                'receiptLinePrice' => round($price, 2),
                'receiptLineQuantity' => $quantity,
                'receiptLineTotal' => round($lineTotal, 2),
                'taxPercent' => $taxPercent,
                'taxID' => $taxId,
                'taxCode' => $taxCode,
            ];

            $taxKey = "{$taxId}_{$taxPercent}";
            if (!isset($taxGroups[$taxKey])) {
                $taxGroups[$taxKey] = [
                    'taxID' => $taxId,
                    'taxPercent' => $taxPercent,
                    'taxCode' => $taxCode,
                    'taxAmount' => 0,
                    'salesAmountWithTax' => 0,
                ];
            }
            $taxGroups[$taxKey]['taxAmount'] += $taxAmount;
            $taxGroups[$taxKey]['salesAmountWithTax'] += $lineTotal;
        }

        foreach ($taxGroups as $tax) {
            $receiptTaxes[] = [
                'taxID' => $tax['taxID'],
                'taxPercent' => $tax['taxPercent'],
                'taxCode' => $tax['taxCode'],
                'taxAmount' => round($tax['taxAmount'], 2),
                'salesAmountWithTax' => round($tax['salesAmountWithTax'], 2),
            ];
        }

        $receiptTotal = round((float) $creditNote->total, 2);

        $receiptPayments = [
            [
                'moneyTypeCode' => $sale->payment_method ?? 'Cash',
                'paymentAmount' => $receiptTotal,
            ]
        ];

        $receiptData = [
            'receiptType' => 'CreditNote',
            'receiptCurrency' => $currencyCode,
            'invoiceNo' => 'CN-' . strtoupper(substr($creditNote->panier_id, -8)),
            'receiptNotes' => $creditNote->reason ?? 'Credit note',
            'creditDebitNote' => [
                'creditDebitNoteReceiptGlobalNo' => $originalReceipt->receipt_global_no,
                'creditDebitNoteDate' => $originalReceipt->receipt_date->format('Y-m-d\TH:i:s'),
            ],
            'receiptDate' => now()->format('Y-m-d\TH:i:s'),
            'receiptLines' => $receiptLines,
            'receiptTaxes' => $receiptTaxes,
            'receiptPayments' => $receiptPayments,
            'receiptTotal' => $receiptTotal,
        ];

        if ($originalReceipt->buyer_data) {
            $receiptData['buyerData'] = $originalReceipt->buyer_data;
        } elseif ($sale->customer_id) {
            $customer = PanierCustomer::where('panier_id', $sale->customer_id)->first();
            if ($customer) {
                $receiptData['buyerData'] = [
                    'buyerRegisterName' => $customer->name,
                    'buyerTradeName' => $customer->name,
                    'buyerTIN' => $customer->tax_id ?? '',
                    'buyerContacts' => [
                        'phoneNo' => $customer->phone ?? '',
                        'email' => $customer->email ?? '',
                    ],
                    'buyerAddress' => [
                        'street' => $customer->address ?? '',
                        'city' => '',
                        'country' => 'ZW',
                    ],
                ];
            }
        }

        return $zimraService->submitReceipt($receiptData, $deviceId);
    }

    public function searchCreditNotes(string $query = '*', int $limit = 10, int $skip = 0): array
    {
        $queryBuilder = PanierCreditNote::query();

        if ($query !== '*') {
            $queryBuilder->where('panier_id', 'like', "%{$query}%");
        }

        $total = $queryBuilder->count();
        $notes = $queryBuilder->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        return [
            'searched_record_count' => $notes->count(),
            'total_record_count' => $total,
            'searched' => $notes->map(fn($n) => $this->formatCreditNote($n))->toArray(),
        ];
    }

    public function deleteCreditNotes(array $data): array
    {
        $ids = array_column($data, 'id');
        $count = PanierCreditNote::whereIn('panier_id', $ids)->delete();

        return ['deleted_count' => $count];
    }

    protected function formatCreditNote(PanierCreditNote $note): array
    {
        return [
            'id' => $note->panier_id,
            '_id' => $note->panier_id,
            'sale_id' => $note->sale_id,
            'customer_id' => $note->customer_id,
            'total' => (float) $note->total,
            'reason' => $note->reason,
            'zimra_fiscalized' => $note->zimra_fiscalized,
            'zimra_fiscal_code' => $note->zimra_fiscal_code,
            'products' => $note->products,
            'created_at' => $note->created_at?->toIso8601String(),
            'updated_at' => $note->updated_at?->toIso8601String(),
        ];
    }

    // ==================== DELIVERY NOTES ====================

    public function createDeliveryNotes(array $data): array
    {
        $created = [];

        foreach ($data as $noteData) {
            $note = PanierDeliveryNote::create([
                'panier_id' => Str::ulid()->toString(),
                'customer_id' => $noteData['customer_id'] ?? null,
                'delivery_address' => $noteData['delivery_address'] ?? null,
                'recipients' => $noteData['recipients'] ?? null,
                'products' => $noteData['products'] ?? null,
                'panier_data' => $noteData,
                'delivery_date' => $noteData['delivery_date'] ?? null,
            ]);
            $created[] = $this->formatDeliveryNote($note);
        }

        return ['created' => $created];
    }

    public function searchDeliveryNotes(string $query = '*', int $limit = 10, int $skip = 0): array
    {
        $queryBuilder = PanierDeliveryNote::query();

        if ($query !== '*') {
            $queryBuilder->where('panier_id', 'like', "%{$query}%");
        }

        $total = $queryBuilder->count();
        $notes = $queryBuilder->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        return [
            'searched_record_count' => $notes->count(),
            'total_record_count' => $total,
            'searched' => $notes->map(fn($n) => $this->formatDeliveryNote($n))->toArray(),
        ];
    }

    public function deleteDeliveryNotes(array $data): array
    {
        $ids = array_column($data, 'id');
        $count = PanierDeliveryNote::whereIn('panier_id', $ids)->delete();

        return ['deleted_count' => $count];
    }

    protected function formatDeliveryNote(PanierDeliveryNote $note): array
    {
        return [
            'id' => $note->panier_id,
            '_id' => $note->panier_id,
            'customer_id' => $note->customer_id,
            'delivery_address' => $note->delivery_address,
            'recipients' => $note->recipients,
            'products' => $note->products,
            'delivery_date' => $note->delivery_date,
            'created_at' => $note->created_at?->toIso8601String(),
            'updated_at' => $note->updated_at?->toIso8601String(),
        ];
    }

    // ==================== QUOTATIONS ====================

    public function createQuotations(array $data): array
    {
        $created = [];

        foreach ($data as $quotationData) {
            $total = 0;
            $products = $quotationData['products'] ?? [];
            
            foreach ($products as $productItem) {
                $productPrice = $productItem['selling_price'] * $productItem['quantity'];
                $discount = $productItem['discount'] ?? 0;
                $total += $productPrice - $discount;
            }

            $quotation = PanierQuotation::create([
                'panier_id' => Str::ulid()->toString(),
                'customer_id' => $quotationData['customer_id'] ?? null,
                'currency_id' => $quotationData['currency_id'] ?? null,
                'total' => $total,
                'status' => 'pending',
                'valid_until' => $quotationData['valid_until'] ?? null,
                'recipients' => $quotationData['recipients'] ?? null,
                'products' => $products,
                'panier_data' => $quotationData,
            ]);
            $created[] = $this->formatQuotation($quotation);
        }

        return ['created' => $created];
    }

    public function updateQuotations(array $data): array
    {
        $updated = [];

        foreach ($data as $quotationData) {
            $quotation = PanierQuotation::where('panier_id', $quotationData['id'])->first();
            
            if (!$quotation) {
                continue;
            }

            $quotation->update(array_filter([
                'customer_id' => $quotationData['customer_id'] ?? null,
                'currency_id' => $quotationData['currency_id'] ?? null,
                'status' => $quotationData['status'] ?? null,
                'valid_until' => $quotationData['valid_until'] ?? null,
                'products' => $quotationData['products'] ?? null,
            ], fn($v) => $v !== null));

            $updated[] = $this->formatQuotation($quotation->fresh());
        }

        return ['updated' => $updated];
    }

    public function searchQuotations(string $query = '*', int $limit = 10, int $skip = 0): array
    {
        $queryBuilder = PanierQuotation::query();

        if ($query !== '*') {
            $queryBuilder->where('panier_id', 'like', "%{$query}%");
        }

        $total = $queryBuilder->count();
        $quotations = $queryBuilder->orderBy('created_at', 'desc')
            ->skip($skip)
            ->take($limit)
            ->get();

        return [
            'searched_record_count' => $quotations->count(),
            'total_record_count' => $total,
            'searched' => $quotations->map(fn($q) => $this->formatQuotation($q))->toArray(),
        ];
    }

    public function deleteQuotations(array $data): array
    {
        $ids = array_column($data, 'id');
        $count = PanierQuotation::whereIn('panier_id', $ids)->delete();

        return ['deleted_count' => $count];
    }

    public function convertQuotationToInvoice(array $data): array
    {
        $quotation = PanierQuotation::where('panier_id', $data['quotation_id'])->first();
        
        if (!$quotation) {
            return ['error' => 'Quotation not found'];
        }

        $invoiceResult = $this->createInvoices([[
            'customer_id' => $quotation->customer_id,
            'currency_id' => $quotation->currency_id,
            'products' => $quotation->products,
        ]]);

        $quotation->update(['status' => 'converted']);

        return [
            'invoice' => $invoiceResult['created'][0] ?? null,
            'quotation' => $this->formatQuotation($quotation->fresh()),
        ];
    }

    public function convertQuotationToSale(array $data, bool $zimraFiscalize = false): array
    {
        $quotation = PanierQuotation::where('panier_id', $data['quotation_id'])->first();
        
        if (!$quotation) {
            return ['error' => 'Quotation not found'];
        }

        $saleResult = $this->createSale([
            'customer_id' => $quotation->customer_id,
            'currency_id' => $quotation->currency_id,
            'products' => $quotation->products,
        ], $zimraFiscalize, ['type' => $data['payment_method'] ?? 'cash']);

        $quotation->update(['status' => 'converted']);

        return [
            'sale' => $saleResult['created'],
            'quotation' => $this->formatQuotation($quotation->fresh()),
        ];
    }

    protected function formatQuotation(PanierQuotation $quotation): array
    {
        return [
            'id' => $quotation->panier_id,
            '_id' => $quotation->panier_id,
            'customer_id' => $quotation->customer_id,
            'currency_id' => $quotation->currency_id,
            'total' => (float) $quotation->total,
            'status' => $quotation->status,
            'valid_until' => $quotation->valid_until,
            'recipients' => $quotation->recipients,
            'products' => $quotation->products,
            'created_at' => $quotation->created_at?->toIso8601String(),
            'updated_at' => $quotation->updated_at?->toIso8601String(),
        ];
    }
}
