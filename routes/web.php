<?php

use App\Http\Controllers\Auth\OpsiRegistrasiController;
use App\Http\Controllers\Auth\RegistrasiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// PBI-05 · Registrasi akun dengan surat tugas
Route::middleware('guest')->group(function () {
    Route::get('/registrasi', [RegistrasiController::class, 'create'])->name('registrasi.create');
    Route::post('/registrasi', [RegistrasiController::class, 'store'])->name('registrasi.store');
    Route::get('/registrasi/terkirim', [RegistrasiController::class, 'terkirim'])->name('registrasi.terkirim');
    Route::get('/registrasi/opsi/wilayah', [OpsiRegistrasiController::class, 'wilayah'])->name('registrasi.opsi.wilayah');
    Route::get('/registrasi/opsi/instansi', [OpsiRegistrasiController::class, 'instansi'])->name('registrasi.opsi.instansi');
});
