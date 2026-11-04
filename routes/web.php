<?php

use App\Http\Controllers\Admin\WilayahController;
use App\Http\Controllers\Auth\OpsiRegistrasiController;
use App\Http\Controllers\Auth\RegistrasiController;
use App\Http\Middleware\SuperadminAktif;
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

// PBI-08 · Master wilayah Kemendagri (PSKE-19).
Route::middleware(SuperadminAktif::class)->prefix('admin/wilayah')->name('admin.wilayah.')->group(function () {
    Route::get('/', [WilayahController::class, 'index'])->name('index');
    Route::get('/template', [WilayahController::class, 'template'])->name('template');
    Route::post('/impor', [WilayahController::class, 'impor'])->name('impor');
    Route::get('/{wilayah}/edit', [WilayahController::class, 'edit'])->name('edit');
    Route::patch('/{wilayah}', [WilayahController::class, 'update'])->name('update');
});
