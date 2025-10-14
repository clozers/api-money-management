<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ScantransaksiController;
use App\Http\Controllers\TransaksiController;

// Auth routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/registrasi', [AuthController::class, 'registrasi']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $request) => $request->user());

    // Scan nota pakai AI (OCR)
    Route::post('/scan-transaksi', [ScantransaksiController::class, 'scanNota']);
    Route::get('/scan-transaksi', function () {
        return response()->json([
            'message' => 'Gunakan metode POST untuk upload nota.'
        ]);
    });

    // CRUD Transaksi manual
    Route::prefix('transaksi')->group(function () {
        Route::get('/', [TransaksiController::class, 'getTransaksi']);
        Route::post('/', [TransaksiController::class, 'storeTransaksi']);
        Route::put('/{id}', [TransaksiController::class, 'updateTransaksi']);
        Route::delete('/{id}', [TransaksiController::class, 'deleteTransaksi']);
    });
});
