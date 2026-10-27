<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'app' => 'Kawan Pangan - Platform Pemetaan & Penyaluran Pangan Kota',
        'version' => '1.0.0',
        'pbi' => 'PBI-01 Setup repositori, struktur proyek, dan deploy demo',
        'epic' => 'EP-01 Fondasi, Akun, dan Akses',
        'sprint' => 'Sprint 1',
        'author' => 'Naufan Rae Rasyad Torick (Developer PIC PBI-01)',
        'timestamp' => now()->toIso8601String()
    ]);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
