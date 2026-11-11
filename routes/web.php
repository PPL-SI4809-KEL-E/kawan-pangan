<?php

use App\Http\Controllers\Admin\AmbangStatusController;
use App\Http\Controllers\Admin\GudangController;
use App\Http\Controllers\Admin\InstansiController;
use App\Http\Controllers\Admin\KomoditasController;
use App\Http\Controllers\Admin\AkunController;
use App\Http\Controllers\Admin\WilayahController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OpsiRegistrasiController;
use App\Http\Controllers\Auth\RegistrasiController;
use App\Http\Controllers\PanelController;
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

// PBI-04 & PBI-05 · Autentikasi Pengunjung (Tamu)
Route::middleware('guest')->group(function () {
    // PBI-04 · Login (PSKE-16, PSKE-31)
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    // PBI-05 · Registrasi akun dengan surat tugas
    Route::get('/registrasi', [RegistrasiController::class, 'create'])->name('registrasi.create');
    Route::post('/registrasi', [RegistrasiController::class, 'store'])->name('registrasi.store');
    Route::get('/registrasi/terkirim', [RegistrasiController::class, 'terkirim'])->name('registrasi.terkirim');
    Route::get('/registrasi/opsi/wilayah', [OpsiRegistrasiController::class, 'wilayah'])->name('registrasi.opsi.wilayah');
    Route::get('/registrasi/opsi/instansi', [OpsiRegistrasiController::class, 'instansi'])->name('registrasi.opsi.instansi');
});

// PBI-04 · Logout (PSKE-34)
Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// PBI-04 · Panel Sederhana per Role (PSKE-31)
Route::middleware(['auth', 'role:SUPERADMIN'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [PanelController::class, 'admin'])->name('dashboard');
});

Route::middleware(['auth', 'role:KEPALA_WILAYAH'])->prefix('panel/wilayah')->name('panel.wilayah')->group(function () {
    Route::get('/', [PanelController::class, 'wilayah']);
});

Route::middleware(['auth', 'role:BADAN_RESMI'])->prefix('panel/instansi')->name('panel.instansi')->group(function () {
    Route::get('/', [PanelController::class, 'instansi']);
});

// PBI-08 · Master wilayah Kemendagri (PSKE-19).
Route::middleware(SuperadminAktif::class)->prefix('admin/wilayah')->name('admin.wilayah.')->group(function () {
    Route::get('/', [WilayahController::class, 'index'])->name('index');
    Route::get('/template', [WilayahController::class, 'template'])->name('template');
    Route::post('/impor', [WilayahController::class, 'impor'])->name('impor');
    Route::get('/{wilayah}/edit', [WilayahController::class, 'edit'])->name('edit');
    Route::patch('/{wilayah}', [WilayahController::class, 'update'])->name('update');
});

Route::middleware(SuperadminAktif::class)->prefix('admin')->name('admin.')->group(function () {
    // PBI-06 · Verifikasi akun dan penugasan (PSKE-17).
    Route::prefix('akun')->name('akun.')->group(function () {
        Route::get('/', [AkunController::class, 'index'])->name('index');
        Route::get('/cari-wilayah', [AkunController::class, 'cariWilayah'])->name('cari-wilayah');
        Route::get('/{user}', [AkunController::class, 'show'])->name('show');
        Route::get('/{user}/surat-tugas', [AkunController::class, 'unduhSuratTugas'])->name('unduh-surat-tugas');
        Route::post('/{user}/aktivasi', [AkunController::class, 'aktivasi'])->name('aktivasi');
        Route::post('/{user}/tolak', [AkunController::class, 'tolak'])->name('tolak');
        Route::post('/{user}/nonaktifkan', [AkunController::class, 'nonaktifkan'])->name('nonaktifkan');
    });

    Route::get('ambang-status', [AmbangStatusController::class, 'edit'])->name('ambang-status.edit');
    Route::patch('ambang-status', [AmbangStatusController::class, 'update'])->name('ambang-status.update');
    Route::resource('komoditas', KomoditasController::class)->parameters(['komoditas' => 'komoditas'])->except('show');
    Route::resource('instansi', InstansiController::class)->except('show');
    Route::get('gudang/opsi-wilayah', [GudangController::class, 'opsiWilayah'])->name('gudang.opsi-wilayah');
    Route::patch('gudang/{gudang}/status', [GudangController::class, 'status'])->name('gudang.status');
    Route::resource('gudang', GudangController::class)->except('show');
});
