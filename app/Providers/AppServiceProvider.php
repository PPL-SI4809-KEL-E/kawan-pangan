<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Registrasi policy otorisasi wilayah, instansi, dan penugasan.
         *
         * Policy didaftarkan dengan nama model string agar tidak gagal saat
         * kelas model belum dimuat secara fisik ke proyek.
         * Setelah model Laporan, Gudang, dan Pengiriman dibuat, ganti string
         * di bawah dengan `ModelClass::class`.
         *
         * Contoh penggunaan di controller:
         *   $this->authorize('view', $laporan);
         *   Gate::authorize('updateStok', $gudang);
         */
        Gate::policy('App\Models\Laporan', \App\Policies\LaporanPolicy::class);
        Gate::policy('App\Models\Gudang', \App\Policies\GudangPolicy::class);
        Gate::policy('App\Models\Pengiriman', \App\Policies\PengirimanPolicy::class);
    }
}

