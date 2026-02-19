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

// ZIMRA Device Routes
Route::post('/zimra/register', [ZimraController::class, 'register']);
Route::get('/zimra/device-config', [ZimraController::class, 'config']);
Route::get('/zimra/status', [ZimraController::class, 'status']);
Route::post('/zimra/open-day', [ZimraController::class, 'openDay']);
Route::post('/zimra/close-day', [ZimraController::class, 'closeDay']);
Route::get('/zimra/fiscal-day', [ZimraController::class, 'fiscalDayStatus']);
Route::post('/zimra/submit-receipt', [ZimraController::class, 'submitReceipt']);
Route::post('/zimra/submit-file', [ZimraController::class, 'submitFile']);
