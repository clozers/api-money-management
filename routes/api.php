<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ScantransaksiController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\KategoripengeluaranController;

// Auth routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/registrasi', [AuthController::class, 'registrasi']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', [UserController::class, 'getUser']);
    Route::put('/user', [UserController::class, 'update']);

    // Scan nota pakai AI (OCR)
    Route::post('/scan-transaksi', [ScantransaksiController::class, 'scanNota']);
    Route::get('/scan-transaksi', function () {
        return response()->json([
            'message' => 'Gunakan metode POST untuk upload nota.'
        ]);
    });

    Route::get('/home', [HomeController::class, 'index']);

    // CRUD Transaksi manual
    Route::prefix('transaksi')->group(function () {
        Route::get('/', [TransaksiController::class, 'getTransaksi']);
        Route::post('/', [TransaksiController::class, 'storeTransaksi']);
        Route::put('/{id}', [TransaksiController::class, 'updateTransaksi']);
        Route::delete('/{id}', [TransaksiController::class, 'deleteTransaksi']);
    });

    // CRUD Kategori
    Route::prefix('kategori')->group(function () {
        Route::get('/', [KategoripengeluaranController::class, 'index']);
        Route::post('/', [KategoripengeluaranController::class, 'store']);
        Route::get('/{id}', [KategoripengeluaranController::class, 'show']);
        Route::put('/{id}', [KategoripengeluaranController::class, 'update']);
        Route::delete('/{id}', [KategoripengeluaranController::class, 'destroy']);
    });
});
