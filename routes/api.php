<?php

use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\TaxController;
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\DebitNoteController;
use App\Http\Controllers\Api\V1\CreditNoteController;
use App\Http\Controllers\Api\V1\DeliveryNoteController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\ZimraController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panier API Routes
|--------------------------------------------------------------------------
|
| These routes proxy requests to the Panier API for managing your company.
| Rate Limit: 500 API calls every 5 minutes per unique IP Address.
|
*/

Route::prefix('v1')->group(function () {
    // Products
    Route::prefix('product')->group(function () {
        Route::post('/create', [ProductController::class, 'create']);
        Route::put('/update', [ProductController::class, 'update']);
        Route::post('/search', [ProductController::class, 'search']);
        Route::post('/delete', [ProductController::class, 'delete']);
    });

    // Stocks
    Route::prefix('stock')->group(function () {
        Route::post('/add', [StockController::class, 'add']);
        Route::post('/subtract', [StockController::class, 'subtract']);
    });

    // Customers
    Route::prefix('customer')->group(function () {
        Route::post('/create', [CustomerController::class, 'create']);
        Route::put('/update', [CustomerController::class, 'update']);
        Route::post('/search', [CustomerController::class, 'search']);
        Route::post('/delete', [CustomerController::class, 'delete']);
    });

    // Suppliers
    Route::prefix('supplier')->group(function () {
        Route::post('/create', [SupplierController::class, 'create']);
        Route::put('/update', [SupplierController::class, 'update']);
        Route::post('/search', [SupplierController::class, 'search']);
        Route::post('/delete', [SupplierController::class, 'delete']);
    });

    // Taxes
    Route::prefix('tax')->group(function () {
        Route::post('/create', [TaxController::class, 'create']);
        Route::put('/update', [TaxController::class, 'update']);
        Route::post('/search', [TaxController::class, 'search']);
        Route::post('/delete', [TaxController::class, 'delete']);
    });

    // Currencies
    Route::prefix('currency')->group(function () {
        Route::post('/create', [CurrencyController::class, 'create']);
        Route::put('/update', [CurrencyController::class, 'update']);
        Route::post('/search', [CurrencyController::class, 'search']);
        Route::post('/delete', [CurrencyController::class, 'delete']);
    });

    // Sales
    Route::prefix('sale')->group(function () {
        Route::post('/create', [SaleController::class, 'create']);
        Route::post('/search', [SaleController::class, 'search']);
        Route::post('/void', [SaleController::class, 'void']);
        Route::get('/download', [SaleController::class, 'download']);
    });

    // Payment Provider
    Route::prefix('payment-provider')->group(function () {
        Route::get('/check-payment-status', [SaleController::class, 'checkPaymentStatus']);
        Route::post('/confirm-payment', [SaleController::class, 'confirmPayment']);
    });

    // Invoices
    Route::prefix('invoice')->group(function () {
        Route::post('/create', [InvoiceController::class, 'create']);
        Route::put('/update', [InvoiceController::class, 'update']);
        Route::post('/search', [InvoiceController::class, 'search']);
        Route::post('/convert-to-sale', [InvoiceController::class, 'convertToSale']);
        Route::get('/download', [InvoiceController::class, 'download']);
        Route::post('/delete', [InvoiceController::class, 'delete']);
    });

    // Debit Notes
    Route::prefix('debit-note')->group(function () {
        Route::post('/create', [DebitNoteController::class, 'create']);
        Route::post('/search', [DebitNoteController::class, 'search']);
        Route::get('/download', [DebitNoteController::class, 'download']);
        Route::post('/delete', [DebitNoteController::class, 'delete']);
    });

    // Credit Notes
    Route::prefix('credit-note')->group(function () {
        Route::post('/create', [CreditNoteController::class, 'create']);
        Route::post('/search', [CreditNoteController::class, 'search']);
        Route::get('/download', [CreditNoteController::class, 'download']);
        Route::post('/delete', [CreditNoteController::class, 'delete']);
    });

    // Delivery Notes
    Route::prefix('delivery-note')->group(function () {
        Route::post('/create', [DeliveryNoteController::class, 'create']);
        Route::post('/search', [DeliveryNoteController::class, 'search']);
        Route::get('/download', [DeliveryNoteController::class, 'download']);
        Route::post('/delete', [DeliveryNoteController::class, 'delete']);
    });

    // Quotations
    Route::prefix('quotation')->group(function () {
        Route::post('/create', [QuotationController::class, 'create']);
        Route::put('/update', [QuotationController::class, 'update']);
        Route::post('/search', [QuotationController::class, 'search']);
        Route::post('/convert-to-invoice', [QuotationController::class, 'convertToInvoice']);
        Route::post('/convert-to-sale', [QuotationController::class, 'convertToSale']);
        Route::get('/download', [QuotationController::class, 'download']);
        Route::post('/delete', [QuotationController::class, 'delete']);
    });

    // ZIMRA Fiscalisation
    Route::prefix('zimra')->group(function () {
        Route::get('/open-day', [ZimraController::class, 'openDay']);
        Route::get('/close-day', [ZimraController::class, 'closeDay']);
        Route::post('/fiscalize', [ZimraController::class, 'fiscalize']);
        Route::get('/fiscalize-status', [ZimraController::class, 'fiscalizeStatus']);
    });
});
