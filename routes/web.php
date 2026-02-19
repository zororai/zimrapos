<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ZimraController;

Route::get('/', function () {
    return view('welcome');
});

// ZIMRA Configuration Routes
Route::post('/zimra/config', [ZimraController::class, 'storeConfig']);
Route::get('/zimra/config', [ZimraController::class, 'getActiveConfig']);
Route::put('/zimra/config/{id}', [ZimraController::class, 'updateConfig']);

// ZIMRA Device Routes
Route::post('/zimra/register', [ZimraController::class, 'register']);
Route::get('/zimra/device-config', [ZimraController::class, 'config']);
