<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ZimraController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/zimra', function () {
    return view('zimra');
});

// ZIMRA Configuration Routes
Route::post('/zimra/config', [ZimraController::class, 'storeConfig']);
Route::get('/zimra/config', [ZimraController::class, 'getActiveConfig']);
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
Route::post('/zimra/submit-receipt', [ZimraController::class, 'submitReceipt']);
Route::post('/zimra/submit-file', [ZimraController::class, 'submitFile']);
Route::get('/zimra/receipts', [ZimraController::class, 'getReceipts']);
Route::get('/zimra/receipts/{id}/pdf', [ZimraController::class, 'downloadReceiptPdf']);

// Test Route
Route::get('/test-zimra', [\App\Http\Controllers\ZimraTestController::class, 'test']);
