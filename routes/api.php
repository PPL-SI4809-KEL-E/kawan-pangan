<?php

use App\Http\Controllers\PetaChoroplethController;
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
        'timestamp' => now()->toIso8601String(),
    ]);
});

// PSKE-69 (PSKE-70/71/72): Data peta choropleth diambil per level yang dibuka.
Route::get('/peta', [PetaChoroplethController::class, 'apiLevel'])->name('api.peta');
