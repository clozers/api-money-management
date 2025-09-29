<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotaController;
use App\Http\Controllers\AuthController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/scan-nota', [NotaController::class, 'scanNota']);
Route::get('/scan-nota', function () {
    return response()->json([
        "message" => "Gunakan POST untuk upload nota"
    ]);
});

Route::post('/login', [AuthController::class, 'login']);

