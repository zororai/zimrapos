<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ZimraController;
use App\Http\Controllers\DashboardController;

// Public landing page
Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/dashboard');
    }
    return view('landing');
})->name('home');

Route::get('/pricing', function () {
    return view('pricing');
})->name('pricing');

// Authenticated + active subscription required
Route::middleware(['auth', 'subscription'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/zimra', function () {
        return view('zimra');
    })->name('zimra');

    // ZIMRA Configuration Routes
    Route::post('/zimra/config', [ZimraController::class, 'storeConfig']);
    Route::get('/zimra/config', [ZimraController::class, 'getActiveConfig']);
    Route::get('/zimra/configs', [ZimraController::class, 'getAllConfigs']);
    Route::get('/zimra/config/{id}', [ZimraController::class, 'getConfigById']);
    Route::post('/zimra/config/{id}/activate', [ZimraController::class, 'setActiveConfig']);
    Route::put('/zimra/config/{id}', [ZimraController::class, 'updateConfig']);
    Route::delete('/zimra/config/{id}', [ZimraController::class, 'deleteConfig']);
    Route::delete('/zimra/device-registration', [ZimraController::class, 'clearDeviceRegistration']);

    // ZIMRA Device Routes
    Route::post('/zimra/register', [ZimraController::class, 'register']);
    Route::post('/zimra/upload-certificates', [ZimraController::class, 'uploadCertificates']);
    Route::get('/zimra/device-config', [ZimraController::class, 'config']);
    Route::get('/zimra/status', [ZimraController::class, 'status']);
    Route::post('/zimra/open-day', [ZimraController::class, 'openDay']);
    Route::post('/zimra/close-day', [ZimraController::class, 'closeDay']);
    Route::post('/zimra/force-close-day', [ZimraController::class, 'forceCloseDay']);
    Route::get('/zimra/fiscal-day', [ZimraController::class, 'fiscalDayStatus']);
    Route::post('/zimra/sync-fiscal-day', [ZimraController::class, 'syncFiscalDay']);
    Route::post('/zimra/submit-receipt', [ZimraController::class, 'submitReceipt']);
    Route::post('/zimra/submit-credit-note', [ZimraController::class, 'submitCreditNote']);
    Route::post('/zimra/submit-file', [ZimraController::class, 'submitFile']);
    Route::get('/zimra/receipts', [ZimraController::class, 'getReceipts']);
    Route::get('/zimra/invoices', [ZimraController::class, 'getInvoices']);
    Route::get('/zimra/receipts/{id}/pdf', [ZimraController::class, 'downloadReceiptPdf']);
    Route::get('/zimra/next-invoice-no', [ZimraController::class, 'getNextInvoiceNo']);
    Route::get('/zimra/tax-config', [ZimraController::class, 'getTaxConfig']);
    Route::get('/zimra/taxes', [ZimraController::class, 'getTaxes']);
    Route::post('/zimra/taxes/create', [ZimraController::class, 'createTax']);
    Route::post('/zimra/taxes/update', [ZimraController::class, 'updateTax']);
    Route::post('/zimra/taxes/delete', [ZimraController::class, 'deleteTax']);
});

// Test Route (dev only)
Route::get('/test-zimra', [\App\Http\Controllers\ZimraTestController::class, 'test']);
